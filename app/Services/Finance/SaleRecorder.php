<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductionJob;

/**
 * Posts the financial breakdown of a paid order to the ledger.
 *
 * For every item: artist earning (held until the settlement policy releases it),
 * platform margin and the base cost collected. Per order: delivery income, discounts
 * (borne by the platform – artist earnings are never reduced by promotions) and
 * payment processing fees. Production cost is posted when a partner dispatches a job.
 */
class SaleRecorder
{
    public function __construct(private Ledger $ledger) {}

    public function recordPaidOrder(Order $order, Payment $payment): void
    {
        $order->loadMissing('items');
        $currency = $order->currency;
        $entries = [];

        foreach ($order->items as $item) {
            $refs = ['order_id' => $order->id, 'order_item_id' => $item->id];
            $label = "{$order->number} · {$item->title} × {$item->quantity}";

            $entries[] = $this->ledger->entry("sale:item:{$item->id}:artist", LedgerAccount::Artist, $item->artist_profile_id, LedgerBucket::Held, LedgerEntryType::SaleEarning, $item->unit_artist_earning_amount * $item->quantity, $currency, $label, $refs);
            $entries[] = $this->ledger->entry("sale:item:{$item->id}:margin", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::PlatformMargin, $item->unit_platform_margin_amount * $item->quantity, $currency, $label, $refs);
            $entries[] = $this->ledger->entry("sale:item:{$item->id}:base", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::CostRecovery, $item->unit_base_cost_amount * $item->quantity, $currency, $label, $refs);
        }

        $orderRefs = ['order_id' => $order->id];
        $entries[] = $this->ledger->entry("sale:order:{$order->id}:delivery", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::DeliveryIncome, $order->delivery_amount, $currency, "{$order->number} delivery", $orderRefs);
        $entries[] = $this->ledger->entry("sale:order:{$order->id}:discount", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::DiscountCost, -$order->discount_amount, $currency, "{$order->number} discount", $orderRefs);

        if ($payment->fee_amount > 0) {
            $entries[] = $this->ledger->entry("fee:payment:{$payment->id}:platform", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::ProcessingFee, -$payment->fee_amount, $currency, "{$order->number} {$payment->provider} fee", $orderRefs);
            $entries[] = $this->ledger->entry("fee:payment:{$payment->id}:processor", LedgerAccount::Processor, null, LedgerBucket::Fees, LedgerEntryType::ProcessingFee, $payment->fee_amount, $currency, "{$order->number} {$payment->provider} fee", $orderRefs);
        }

        $this->ledger->post($entries);
    }

    public function recordProductionCost(ProductionJob $job, string $suffix = 'dispatched'): void
    {
        $item = $job->orderItem;
        $cost = $job->unit_cost_amount * $job->quantity;
        $refs = ['order_id' => $item->order_id, 'order_item_id' => $item->id, 'production_job_id' => $job->id];
        $label = "{$job->reference} production cost";

        $this->ledger->post([
            $this->ledger->entry("production:job:{$job->id}:partner", LedgerAccount::Partner, $job->printing_partner_id, LedgerBucket::Payable, LedgerEntryType::ProductionCost, $cost, $job->currency, $label." ({$suffix})", $refs),
            $this->ledger->entry("production:job:{$job->id}:platform", LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::ProductionCost, -$cost, $job->currency, $label." ({$suffix})", $refs),
        ]);
    }
}
