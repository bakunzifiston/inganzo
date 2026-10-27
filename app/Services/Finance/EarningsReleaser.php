<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\OrderItem;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Settlement policy: an artist's earning for an item moves from "held" to "available"
 * once the item has been delivered and the configured hold period (payouts.hold_days)
 * has passed without a full refund. Runs daily from the scheduler and is idempotent.
 */
class EarningsReleaser
{
    public function __construct(private Ledger $ledger, private Settings $settings) {}

    public function releaseEligible(): int
    {
        $cutoff = now()->subDays($this->settings->integer('payouts.hold_days'));
        $released = 0;

        OrderItem::query()
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', $cutoff)
            ->whereHas('order', fn ($q) => $q->countableSales())
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('ledger_entries')
                ->whereColumn('ledger_entries.order_item_id', 'order_items.id')
                ->where('ledger_entries.type', LedgerEntryType::EarningRelease->value)
                ->where('ledger_entries.bucket', LedgerBucket::Available->value))
            ->with('order:id,number,currency')
            ->chunkById(200, function ($items) use (&$released) {
                foreach ($items as $item) {
                    $released += $this->release($item) ? 1 : 0;
                }
            });

        return $released;
    }

    public function release(OrderItem $item): bool
    {
        return DB::transaction(function () use ($item) {
            $held = $this->ledger->itemArtistBalances($item->id)['held'];

            if ($held <= 0) {
                return false;
            }

            $refs = ['order_id' => $item->order_id, 'order_item_id' => $item->id];
            $label = "{$item->order->number} · earnings released";

            return $this->ledger->post([
                $this->ledger->entry("release:item:{$item->id}:held", LedgerAccount::Artist, $item->artist_profile_id, LedgerBucket::Held, LedgerEntryType::EarningRelease, -$held, $item->order->currency, $label, $refs),
                $this->ledger->entry("release:item:{$item->id}:available", LedgerAccount::Artist, $item->artist_profile_id, LedgerBucket::Available, LedgerEntryType::EarningRelease, $held, $item->order->currency, $label, $refs),
            ]) > 0;
        });
    }
}
