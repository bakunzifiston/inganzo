<?php

namespace App\Http\Controllers\Partner;

use App\Enums\ProductionJobStatus;
use App\Enums\QcResult;
use App\Http\Controllers\Controller;
use App\Models\ProductionJob;
use App\Models\ProductionJobEvent;
use App\Services\Fulfillment\ProductionWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Production queue for printing-partner staff. Every lookup is scoped to the
 * signed-in user's facility; jobs of other partners resolve to 404.
 */
class JobController extends Controller
{
    public const TABS = ['open', 'new', 'ready', 'issues', 'done', 'all'];

    public function __construct(private ProductionWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $partnerId = $request->user()->printingPartnerId();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'open';
        $search = trim((string) $request->query('q'));

        $base = fn () => ProductionJob::query()->where('printing_partner_id', $partnerId);

        $jobs = $this->applyTab($base(), $tab)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('reference', 'ilike', '%'.$search.'%')
                ->orWhereHas('orderItem.order', fn (Builder $o) => $o->where('number', 'ilike', '%'.$search.'%'))))
            ->with(['orderItem.order', 'orderItem.artworkVersion'])
            ->orderByRaw("CASE WHEN status IN ('failed', 'cancelled', 'dispatched') THEN 1 ELSE 0 END")
            ->orderBy('due_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [];
        foreach (self::TABS as $key) {
            $counts[$key] = $this->applyTab($base(), $key)->count();
        }

        return view('partner.jobs.index', [
            'jobs' => $jobs,
            'tab' => $tab,
            'search' => $search,
            'counts' => $counts,
            'delayedCount' => $base()->delayed()->count(),
            'charts' => $this->charts($partnerId),
            'issueCount' => $base()->active()->where('has_issue', true)->count(),
            'stats' => [
                'new' => $base()->where('status', ProductionJobStatus::Assigned)->count(),
                'in_production' => $base()->whereIn('status', [ProductionJobStatus::Accepted, ProductionJobStatus::InProduction, ProductionJobStatus::QualityCheck])->count(),
                'ready' => $base()->where('status', ProductionJobStatus::ReadyForDispatch)->count(),
                'dispatched_week' => $base()->where('status', ProductionJobStatus::Dispatched)->where('dispatched_at', '>=', now()->subDays(7))->sum('quantity'),
                'due_today' => $base()->active()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
                // Hours from assignment to passing QC, over the last 30 days.
                'turnaround_jobs' => $base()->whereNotNull('completed_at')->where('completed_at', '>=', now()->subDays(30))->count(),
                'turnaround_hours' => (int) round((float) $base()->whereNotNull('completed_at')->where('completed_at', '>=', now()->subDays(30))
                    ->selectRaw('AVG(EXTRACT(EPOCH FROM (completed_at - assigned_at)) / 3600) AS hours')->value('hours')),
                // A failed quality check sends the job back to printing.
                'qc_failures' => ProductionJobEvent::query()->where('type', 'qc')->where('to_status', ProductionJobStatus::InProduction->value)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('production_job_id', $base()->select('id'))->count(),
            ],
            'partner' => $request->user()->partnerMembership->partner,
        ]);
    }

    /**
     * Units dispatched per day for the last 14 days and open jobs by stage.
     *
     * @return array{days: list<string>, dispatched: list<int>, stages: array<string, int>}
     */
    private function charts(?int $partnerId): array
    {
        $from = now()->subDays(13)->startOfDay();
        $rows = ProductionJob::query()->where('printing_partner_id', $partnerId)
            ->where('dispatched_at', '>=', $from)
            ->selectRaw("to_char(dispatched_at AT TIME ZONE ?, 'YYYY-MM-DD') AS day, SUM(quantity) AS units", [config('app.timezone')])
            ->groupBy('day')->pluck('units', 'day');

        $days = $dispatched = [];
        for ($day = $from->copy(); $day <= now(); $day->addDay()) {
            $days[] = $day->translatedFormat('j M');
            $dispatched[] = (int) ($rows[$day->format('Y-m-d')] ?? 0);
        }

        $stages = [];
        foreach ([ProductionJobStatus::Assigned, ProductionJobStatus::Accepted, ProductionJobStatus::InProduction, ProductionJobStatus::QualityCheck, ProductionJobStatus::ReadyForDispatch] as $status) {
            $stages[$status->label()] = ProductionJob::query()->where('printing_partner_id', $partnerId)->where('status', $status)->count();
        }

        return ['days' => $days, 'dispatched' => $dispatched, 'stages' => $stages];
    }

    public function show(Request $request, ProductionJob $job): View
    {
        $this->authorizeJob($request, $job, 'view');

        $job->load([
            'orderItem.order.address',
            'orderItem.artworkVersion.artwork',
            'orderItem.artistProduct.product.printAreas',
            'orderItem.artistProduct.product.mockups',
            'orderItem.variant.color',
            'events.user',
            'reprintOf',
        ]);

        $order = $job->orderItem->order;

        // Other items of the same order that this facility can ship together.
        $readyItems = $order->items()
            ->whereHas('activeJob', fn (Builder $q) => $q->where('printing_partner_id', $job->printing_partner_id)->where('status', ProductionJobStatus::ReadyForDispatch))
            ->get();

        return view('partner.jobs.show', [
            'job' => $job,
            'item' => $job->orderItem,
            'order' => $order,
            'readyItems' => $readyItems,
        ]);
    }

    public function advance(Request $request, ProductionJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $job, 'update');

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (ProductionJobStatus $s) => $s->value, $job->status->partnerTransitions()))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $job = $this->workflow->advance($job, ProductionJobStatus::from($validated['status']), $request->user(), $validated['notes'] ?? null);

        return back()->with('success', __('Job :ref is now ":status".', ['ref' => $job->reference, 'status' => $job->status->label()]));
    }

    public function qualityCheck(Request $request, ProductionJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $job, 'update');

        $validated = $request->validate([
            'qc_result' => ['required', Rule::enum(QcResult::class)],
            'qc_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = QcResult::from($validated['qc_result']);
        $this->workflow->recordQualityCheck($job, $result, $request->user(), $validated['qc_notes'] ?? null);

        return back()->with('success', $result === QcResult::Passed
            ? __('Quality check passed. The item is ready for dispatch.')
            : __('Quality check failed. The job is back in production for a reprint.'));
    }

    public function issue(Request $request, ProductionJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $job, 'update');

        $validated = $request->validate(['notes' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->workflow->reportIssue($job, $request->user(), $validated['notes']);

        return back()->with('success', __('Issue reported. Our operations team has been notified.'));
    }

    public function fail(Request $request, ProductionJob $job): RedirectResponse
    {
        $this->authorizeJob($request, $job, 'update');

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $this->workflow->fail($job, $request->user(), $validated['reason']);

        return back()->with('success', __('Job marked as failed. Operations will arrange a reprint.'));
    }

    /**
     * Hide other facilities' jobs entirely (404), then apply the policy.
     */
    private function authorizeJob(Request $request, ProductionJob $job, string $ability): void
    {
        abort_unless($job->printing_partner_id === $request->user()->printingPartnerId(), 404);

        Gate::authorize($ability, $job);
    }

    /**
     * @param  Builder<ProductionJob>  $query
     * @return Builder<ProductionJob>
     */
    private function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'open' => $query->active(),
            'new' => $query->where('status', ProductionJobStatus::Assigned),
            'ready' => $query->where('status', ProductionJobStatus::ReadyForDispatch),
            'issues' => $query->where(fn (Builder $q) => $q->where('has_issue', true)->orWhere('status', ProductionJobStatus::Failed)),
            'done' => $query->whereIn('status', [ProductionJobStatus::Dispatched, ProductionJobStatus::Cancelled]),
            default => $query,
        };
    }
}
