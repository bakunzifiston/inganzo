<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ProductionJobStatus;
use App\Enums\RefundReason;
use App\Enums\RefundStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\RefundUpdated;
use App\Services\AuditLogger;
use App\Services\Fulfillment\OrderProgress;
use App\Services\Inventory\StockManager;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Refund workflow: requested → approved → processed (money returned) | rejected.
 *
 * When processed, the refunded amount is allocated across the affected lines (and
 * delivery) in proportion to what the customer paid. For each line the artist's
 * earning is reversed in the same proportion – from held earnings first, then from
 * available earnings – and the platform absorbs the rest.
 */
class RefundService
{
    public function __construct(
        private Ledger $ledger,
        private AuditLogger $audit,
        private OrderProgress $progress,
        private StockManager $stock,
    ) {}

    public function request(Order $order, ?OrderItem $item, int $amount, RefundReason $reason, ?string $note, ?User $requester): Refund
    {
        return DB::transaction(function () use ($order, $item, $amount, $reason, $note, $requester) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $this->assertRefundable($order, $item, $amount);

            if ($order->refunds()->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved])->exists()) {
                throw ValidationException::withMessages(['amount' => __('A refund for this order is already being reviewed.')]);
            }

            return Refund::query()->create([
                'reference' => Reference::generate('RF', Refund::class),
                'order_id' => $order->id,
                'order_item_id' => $item?->id,
                'payment_id' => $order->successfulPayment?->id,
                'amount' => $amount,
                'currency' => $order->currency,
                'reason' => $reason,
                'status' => RefundStatus::Requested,
                'customer_note' => $note,
                'requested_by' => $requester?->id,
            ]);
        });
    }

    public function approve(Refund $refund, User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($refund, $reviewer, $note) {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            $this->assertStatus($refund, [RefundStatus::Requested]);

            $refund->forceFill([
                'status' => RefundStatus::Approved,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ])->save();

            $this->audit->log('refund.approved', $refund, ['amount' => $refund->amount], $reviewer);
        });

        $this->notify($refund->fresh());
    }

    public function reject(Refund $refund, User $reviewer, string $note): void
    {
        DB::transaction(function () use ($refund, $reviewer, $note) {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            $this->assertStatus($refund, [RefundStatus::Requested, RefundStatus::Approved]);

            $refund->forceFill([
                'status' => RefundStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'admin_note' => $note,
            ])->save();

            $this->audit->log('refund.rejected', $refund, ['note' => $note], $reviewer);
        });

        $this->notify($refund->fresh());
    }

    /**
     * Record that money has been returned to the customer and post ledger reversals.
     * $providerReference is the mobile-money / bank reference of the outgoing transfer.
     */
    public function process(Refund $refund, User $actor, string $providerReference): Refund
    {
        $refund = DB::transaction(function () use ($refund, $actor, $providerReference) {
            $refund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            $this->assertStatus($refund, [RefundStatus::Approved]);

            $order = Order::query()->lockForUpdate()->with('items')->findOrFail($refund->order_id);
            $item = $refund->order_item_id ? $order->items->firstWhere('id', $refund->order_item_id) : null;
            $this->assertRefundable($order, $item, $refund->amount);

            $this->postReversals($order, $item, $refund);

            $order->refunded_amount += $refund->amount;
            $order->payment_status = $order->refunded_amount >= $order->total_amount
                ? OrderPaymentStatus::Refunded
                : OrderPaymentStatus::PartiallyRefunded;
            $order->save();

            $refund->forceFill([
                'status' => RefundStatus::Processed,
                'provider_reference' => $providerReference,
                'processed_at' => now(),
                'reviewed_by' => $refund->reviewed_by ?? $actor->id,
            ])->save();

            $this->cancelFullyRefundedItems($order->fresh('items'));

            if ($order->payment_status === OrderPaymentStatus::Refunded && $order->items->every(fn ($i) => $i->delivered_at === null)) {
                $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => 'refunded'])->save();
            }

            $this->progress->sync($order);
            $this->audit->log('refund.processed', $refund, ['amount' => $refund->amount, 'provider_reference' => $providerReference], $actor);

            return $refund;
        });

        $this->notify($refund);

        return $refund;
    }

    /**
     * Admin shortcut: create, approve and process a refund in one step.
     */
    public function issue(Order $order, ?OrderItem $item, int $amount, RefundReason $reason, User $actor, string $providerReference, ?string $note = null): Refund
    {
        $refund = $this->request($order, $item, $amount, $reason, null, $actor);
        $this->approve($refund, $actor, $note);

        return $this->process($refund, $actor, $providerReference);
    }

    public function maxRefundable(Order $order, ?OrderItem $item): int
    {
        if (! $order->isPaid()) {
            return 0;
        }

        $orderRemaining = $order->total_amount - $order->refunded_amount;

        if ($item === null) {
            return $orderRemaining;
        }

        return min($orderRemaining, $item->netLineAmount() - $item->refunded_amount);
    }

    private function assertRefundable(Order $order, ?OrderItem $item, int $amount): void
    {
        if ($item !== null && $item->order_id !== $order->id) {
            throw ValidationException::withMessages(['order_item_id' => __('That item does not belong to this order.')]);
        }

        $max = $this->maxRefundable($order, $item);

        if ($amount <= 0 || $amount > $max) {
            throw ValidationException::withMessages(['amount' => __('The refund must be between :min and :max.', [
                'min' => Money::format(1, $order->currency),
                'max' => Money::format($max, $order->currency),
            ])]);
        }
    }

    /**
     * @param  list<RefundStatus>  $allowed
     */
    private function assertStatus(Refund $refund, array $allowed): void
    {
        if (! in_array($refund->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('This refund is already :status.', ['status' => $refund->status->label()])]);
        }
    }

    private function postReversals(Order $order, ?OrderItem $item, Refund $refund): void
    {
        $items = $item ? collect([$item]) : $order->items;

        // Weights: what is still refundable on each line, plus undelivered delivery fee for order-level refunds.
        $weights = [];
        foreach ($items as $line) {
            $weights['item:'.$line->id] = max(0, $line->netLineAmount() - $line->refunded_amount);
        }
        if ($item === null) {
            $refundedSoFar = array_sum($order->items->pluck('refunded_amount')->all());
            $deliveryRefunded = max(0, $order->refunded_amount - $refundedSoFar);
            $weights['delivery'] = max(0, $order->delivery_amount - $deliveryRefunded);
        }

        $allocation = Money::allocate($refund->amount, $weights);
        $entries = [];
        $platformShare = 0;

        foreach ($items as $line) {
            $portion = $allocation['item:'.$line->id] ?? 0;
            if ($portion <= 0) {
                continue;
            }

            $balances = $this->ledger->itemArtistBalances($line->id);
            $remainingEarning = $balances['held'] + $balances['available'];
            $refundableNet = max(1, $line->netLineAmount() - $line->refunded_amount);
            $artistReversal = min($remainingEarning, intdiv($remainingEarning * $portion, $refundableNet));

            if ($portion >= $refundableNet) {
                $artistReversal = $remainingEarning;
            }

            $fromHeld = min($artistReversal, max(0, $balances['held']));
            $fromAvailable = $artistReversal - $fromHeld;
            $refs = ['order_id' => $order->id, 'order_item_id' => $line->id, 'refund_id' => $refund->id];
            $label = "{$refund->reference} refund · {$line->title}";

            $entries[] = $this->ledger->entry("refund:{$refund->id}:item:{$line->id}:held", LedgerAccount::Artist, $line->artist_profile_id, LedgerBucket::Held, LedgerEntryType::RefundReversal, -$fromHeld, $order->currency, $label, $refs);
            $entries[] = $this->ledger->entry("refund:{$refund->id}:item:{$line->id}:available", LedgerAccount::Artist, $line->artist_profile_id, LedgerBucket::Available, LedgerEntryType::RefundReversal, -$fromAvailable, $order->currency, $label, $refs);

            $platformShare += $portion - $artistReversal;
            $line->refunded_amount += $portion;
            $line->save();
        }

        $platformShare += $allocation['delivery'] ?? 0;
        $entries[] = $this->ledger->entry("refund:{$refund->id}:platform", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::RefundReversal, -$platformShare, $order->currency, "{$refund->reference} refund", ['order_id' => $order->id, 'refund_id' => $refund->id]);

        $this->ledger->post($entries);
    }

    private function cancelFullyRefundedItems(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->refunded_amount < $item->netLineAmount() || in_array($item->fulfillment_status, [OrderItemStatus::Delivered, OrderItemStatus::Shipped], true)) {
                continue;
            }

            $item->forceFill(['fulfillment_status' => OrderItemStatus::Refunded])->save();
            // Unshipped ready-made goods go back on the shelf.
            $this->stock->release($item);
            $item->productionJobs()->whereIn('status', ProductionJobStatus::activeValues())->get()->each(function ($job) {
                $job->forceFill(['status' => ProductionJobStatus::Cancelled])->save();
                $job->events()->create(['type' => 'status', 'from_status' => null, 'to_status' => ProductionJobStatus::Cancelled->value, 'notes' => 'Item refunded', 'created_at' => now()]);
            });
        }
    }

    private function notify(Refund $refund): void
    {
        $order = $refund->order;
        $notification = new RefundUpdated($refund);

        $order->user
            ? $order->user->notify($notification)
            : Notification::route('mail', $order->email)->notify($notification);
    }
}
