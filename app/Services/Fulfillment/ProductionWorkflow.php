<?php

namespace App\Services\Fulfillment;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\QcResult;
use App\Models\PrintingPartner;
use App\Models\ProductionJob;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Finance\SaleRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State machine for production jobs:
 *
 *   assigned → accepted → in_production → quality_check → ready_for_dispatch → dispatched
 *                                               ↘ (QC failed) in_production (reprint in place)
 *   any active state → failed (with reason) → admin may create a reprint job
 */
class ProductionWorkflow
{
    public function __construct(
        private OrderProgress $progress,
        private SaleRecorder $sales,
        private PartnerAssigner $assigner,
        private AuditLogger $audit,
    ) {}

    public function advance(ProductionJob $job, ProductionJobStatus $to, User $actor, ?string $notes = null): ProductionJob
    {
        return $this->mutate($job, $actor, function (ProductionJob $job) use ($to, $actor, $notes) {
            if (! in_array($to, $job->status->partnerTransitions(), true)) {
                throw ValidationException::withMessages(['status' => __('A job that is :from cannot move to :to.', ['from' => $job->status->label(), 'to' => $to->label()])]);
            }

            $from = $job->status;
            $job->status = $to;
            match ($to) {
                ProductionJobStatus::Accepted => $job->accepted_at = now(),
                ProductionJobStatus::InProduction => $job->started_at = now(),
                default => null,
            };
            $job->save();
            $this->event($job, $actor, 'status', $from, $to, $notes);

            if (in_array($to, [ProductionJobStatus::Accepted, ProductionJobStatus::InProduction, ProductionJobStatus::QualityCheck], true)) {
                $job->orderItem->forceFill(['fulfillment_status' => OrderItemStatus::InProduction])->save();
            }
        });
    }

    public function recordQualityCheck(ProductionJob $job, QcResult $result, User $actor, ?string $notes = null): ProductionJob
    {
        return $this->mutate($job, $actor, function (ProductionJob $job) use ($result, $actor, $notes) {
            if ($job->status !== ProductionJobStatus::QualityCheck) {
                throw ValidationException::withMessages(['qc_result' => __('Quality checks can only be recorded for jobs in quality check.')]);
            }

            if ($result === QcResult::Failed && blank($notes)) {
                throw ValidationException::withMessages(['qc_notes' => __('Describe what failed the quality check.')]);
            }

            $from = $job->status;
            $job->qc_result = $result;
            $job->qc_notes = $notes;

            if ($result === QcResult::Passed) {
                $job->status = ProductionJobStatus::ReadyForDispatch;
                $job->completed_at = now();
                $job->orderItem->forceFill(['fulfillment_status' => OrderItemStatus::Ready])->save();
            } else {
                // Reprint in place at the same partner; the failed attempt is kept in the event log.
                $job->status = ProductionJobStatus::InProduction;
                $job->has_issue = true;
            }

            $job->save();
            $this->event($job, $actor, 'qc', $from, $job->status, trim(__('QC :result', ['result' => $result->label()]).'. '.$notes));
        });
    }

    public function reportIssue(ProductionJob $job, User $actor, string $notes): ProductionJob
    {
        return $this->mutate($job, $actor, function (ProductionJob $job) use ($actor, $notes) {
            if (! $job->status->isActive()) {
                throw ValidationException::withMessages(['notes' => __('Issues can only be reported on active jobs.')]);
            }
            $job->forceFill(['has_issue' => true])->save();
            $this->event($job, $actor, 'issue', null, null, $notes);
        });
    }

    /**
     * Mark a job as failed. If the partner incurred material cost it is posted to the ledger.
     */
    public function fail(ProductionJob $job, User $actor, string $reason, bool $costIncurred = false): ProductionJob
    {
        $job = $this->mutate($job, $actor, function (ProductionJob $job) use ($actor, $reason) {
            if (! $job->status->isActive()) {
                throw ValidationException::withMessages(['reason' => __('Only active jobs can be marked as failed.')]);
            }
            $from = $job->status;
            $job->forceFill(['status' => ProductionJobStatus::Failed, 'failed_at' => now(), 'failure_reason' => $reason, 'has_issue' => true])->save();
            $job->orderItem->forceFill(['fulfillment_status' => OrderItemStatus::AwaitingAssignment])->save();
            $this->event($job, $actor, 'status', $from, ProductionJobStatus::Failed, $reason);
        });

        if ($costIncurred) {
            $this->sales->recordProductionCost($job, 'failed');
        }

        $this->audit->log('production.failed', $job, ['reason' => $reason, 'cost_incurred' => $costIncurred], $actor);

        return $job;
    }

    /**
     * Admin: start a new job for a failed item at the same or another partner.
     */
    public function reprint(ProductionJob $failed, User $actor, ?int $partnerId = null): ProductionJob
    {
        if ($failed->status !== ProductionJobStatus::Failed) {
            throw ValidationException::withMessages(['partner' => __('Only failed jobs can be reprinted.')]);
        }

        $partner = $partnerId ? PrintingPartner::query()->findOrFail($partnerId) : $failed->partner;

        return $this->assigner->assign($failed->orderItem, $partner, $actor, $failed);
    }

    private function mutate(ProductionJob $job, User $actor, callable $change): ProductionJob
    {
        $job = DB::transaction(function () use ($job, $change) {
            $job = ProductionJob::query()->lockForUpdate()->with('orderItem')->findOrFail($job->id);
            $change($job);

            return $job;
        });

        $this->progress->sync($job->orderItem->order);

        return $job->fresh(['orderItem', 'events']);
    }

    private function event(ProductionJob $job, User $actor, string $type, ?ProductionJobStatus $from, ?ProductionJobStatus $to, ?string $notes): void
    {
        $job->events()->create([
            'user_id' => $actor->id,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
