<?php

namespace App\Services\Fulfillment;

use App\Enums\OrderItemStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\SellingType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PrintingPartner;
use App\Models\PrintingPartnerService;
use App\Models\ProductionJob;
use App\Models\User;
use App\Notifications\ProductionJobAssigned;
use App\Services\AuditLogger;
use App\Support\Reference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Routes paid order items to printing partners.
 *
 * Eligible partners are active, offer the product (active service) and serve the
 * delivery country/zone. Among them the partner with the lowest open-job load relative
 * to its daily capacity wins, then the lowest priority number. Items with no eligible
 * partner wait in "awaiting printer" for an administrator to assign manually.
 */
class PartnerAssigner
{
    public function __construct(private AuditLogger $audit, private OrderProgress $progress) {}

    public function assignOrder(Order $order): int
    {
        $assigned = 0;

        foreach ($order->items()->where('selling_type', SellingType::PrintOnDemand->value)->whereIn('fulfillment_status', [OrderItemStatus::Pending, OrderItemStatus::AwaitingAssignment])->get() as $item) {
            $partner = $this->eligiblePartners($item)->first();

            if ($partner === null) {
                $item->forceFill(['fulfillment_status' => OrderItemStatus::AwaitingAssignment])->save();

                continue;
            }

            $this->assign($item, $partner, null);
            $assigned++;
        }

        $this->progress->sync($order);

        return $assigned;
    }

    /**
     * @return Collection<int, PrintingPartner>
     */
    public function eligiblePartners(OrderItem $item): Collection
    {
        $order = $item->order;

        return PrintingPartner::query()
            ->where('is_active', true)
            ->whereHas('services', fn (Builder $q) => $q->where('product_id', $item->product_id)->where('is_active', true))
            ->whereHas('serviceAreas', fn (Builder $q) => $q->where('country_id', $order->country_id)
                ->where(fn (Builder $z) => $z->whereNull('delivery_zone_id')->orWhere('delivery_zone_id', $order->delivery_zone_id)))
            ->withCount(['productionJobs as open_jobs_count' => fn (Builder $q) => $q->active()])
            ->get()
            ->sortBy([
                fn (PrintingPartner $a, PrintingPartner $b) => ($a->open_jobs_count / max(1, $a->daily_capacity)) <=> ($b->open_jobs_count / max(1, $b->daily_capacity)),
                fn (PrintingPartner $a, PrintingPartner $b) => $a->priority <=> $b->priority,
            ])
            ->values();
    }

    /**
     * Assign (or reassign) an item. Any open job at another partner is cancelled first.
     */
    public function assign(OrderItem $item, PrintingPartner $partner, ?User $actor, ?ProductionJob $reprintOf = null): ProductionJob
    {
        $job = DB::transaction(function () use ($item, $partner, $actor, $reprintOf) {
            $item = OrderItem::query()->lockForUpdate()->with('order')->findOrFail($item->id);

            if ($item->isFinished()) {
                throw ValidationException::withMessages(['partner' => __('Ready-made products are shipped by their artist, not printed.')]);
            }
            if (! $item->order->isPaid() && $item->order->payment_method !== 'cash_on_delivery') {
                throw ValidationException::withMessages(['partner' => __('Only paid orders can go to production.')]);
            }
            if (in_array($item->fulfillment_status, [OrderItemStatus::Shipped, OrderItemStatus::Delivered, OrderItemStatus::Refunded, OrderItemStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['partner' => __('This item can no longer be assigned.')]);
            }

            $service = PrintingPartnerService::query()
                ->where('printing_partner_id', $partner->id)
                ->where('product_id', $item->product_id)
                ->where('is_active', true)
                ->first();

            if ($service === null || ! $partner->is_active) {
                throw ValidationException::withMessages(['partner' => __(':partner does not produce this product.', ['partner' => $partner->name])]);
            }

            foreach ($item->productionJobs()->whereIn('status', ProductionJobStatus::activeValues())->lockForUpdate()->get() as $open) {
                $open->forceFill(['status' => ProductionJobStatus::Cancelled])->save();
                $open->events()->create(['user_id' => $actor?->id, 'type' => 'assignment', 'from_status' => null, 'to_status' => 'cancelled', 'notes' => __('Reassigned to :partner', ['partner' => $partner->name]), 'created_at' => now()]);
            }

            $job = ProductionJob::query()->create([
                'reference' => Reference::generate('JOB', ProductionJob::class),
                'order_item_id' => $item->id,
                'printing_partner_id' => $partner->id,
                'reprint_of_id' => $reprintOf?->id,
                'status' => ProductionJobStatus::Assigned,
                'quantity' => $item->quantity,
                'unit_cost_amount' => $service->unit_cost_amount,
                'currency' => $item->order->currency,
                'assigned_by' => $actor?->id,
                'assigned_at' => now(),
                'due_at' => now()->addWeekdays($service->production_days)->endOfDay(),
            ]);

            $job->events()->create([
                'user_id' => $actor?->id,
                'type' => 'assignment',
                'to_status' => ProductionJobStatus::Assigned->value,
                'notes' => $reprintOf ? __('Reprint of :ref', ['ref' => $reprintOf->reference]) : ($actor ? __('Assigned manually') : __('Assigned automatically')),
                'created_at' => now(),
            ]);

            $item->forceFill(['fulfillment_status' => OrderItemStatus::Assigned])->save();

            if ($actor) {
                $this->audit->log('production.assigned', $job, ['partner' => $partner->name, 'order_item_id' => $item->id], $actor);
            }

            return $job;
        });

        $this->progress->sync($job->orderItem->order);

        $members = $partner->members()->with('user')->get()->pluck('user')->filter();
        if ($members->isNotEmpty()) {
            Notification::send($members, new ProductionJobAssigned($job));
        }

        return $job;
    }
}
