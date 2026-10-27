<?php

namespace App\Services\Reporting;

use App\Enums\FinishedProductStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\OrderItemStatus;
use App\Enums\SellingType;
use App\Models\ArtistProduct;
use App\Models\ArtistProfile;
use App\Models\FinishedProduct;
use App\Models\FinishedProductVariant;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Artist dashboard figures. "Sales" are items on orders that are paid or partially
 * refunded – pending, failed, cancelled and fully refunded orders are excluded.
 */
class ArtistMetrics
{
    /**
     * @return Builder<OrderItem>
     */
    public function soldItems(ArtistProfile $artist): Builder
    {
        return OrderItem::query()
            ->where('order_items.artist_profile_id', $artist->id)
            ->whereHas('order', fn (Builder $q) => $q->countableSales());
    }

    /**
     * @return array{units: int, orders: int, earnings: int, held: int, available: int, paid_out: int, views: int, published: int}
     */
    public function summary(ArtistProfile $artist, CarbonImmutable $from, ?CarbonImmutable $to = null): array
    {
        $items = $this->soldItems($artist)->whereHas('order', fn (Builder $q) => $q->where('paid_at', '>=', $from)
            ->when($to, fn (Builder $q) => $q->where('paid_at', '<=', $to)));
        $balance = fn (LedgerBucket $bucket) => (int) LedgerEntry::query()
            ->where('account_type', LedgerAccount::Artist)->where('account_id', $artist->id)->where('bucket', $bucket)->sum('amount');

        return [
            'units' => (int) (clone $items)->sum('quantity'),
            'orders' => (int) (clone $items)->distinct()->count('order_id'),
            'earnings' => (int) (clone $items)->sum(DB::raw('unit_artist_earning_amount * quantity')),
            'held' => $balance(LedgerBucket::Held),
            'available' => $balance(LedgerBucket::Available),
            'paid_out' => (int) -LedgerEntry::query()
                ->where('account_type', LedgerAccount::Artist)->where('account_id', $artist->id)
                ->whereIn('type', [LedgerEntryType::Payout, LedgerEntryType::PayoutReversal])
                ->sum('amount'),
            'views' => (int) $artist->artworks()->sum('view_count'),
            'published' => $artist->publishedArtworks()->count(),
        ];
    }

    /**
     * Daily artist earnings and units for a chart, including days without sales.
     *
     * @return array{labels: list<string>, earnings: list<int>, units: list<int>}
     */
    public function trend(ArtistProfile $artist, CarbonImmutable $from): array
    {
        $rows = $this->soldItems($artist)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.paid_at', '>=', $from)
            ->selectRaw("to_char(orders.paid_at AT TIME ZONE ?, 'YYYY-MM-DD') AS day", [config('app.timezone')])
            ->selectRaw('SUM(order_items.unit_artist_earning_amount * order_items.quantity) AS earnings, SUM(order_items.quantity) AS units')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $labels = $earnings = $units = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo(now()); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->translatedFormat('j M');
            $earnings[] = (int) ($rows[$key]->earnings ?? 0);
            $units[] = (int) ($rows[$key]->units ?? 0);
        }

        return ['labels' => $labels, 'earnings' => $earnings, 'units' => $units];
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function topDesigns(ArtistProfile $artist, int $limit = 5): Collection
    {
        return $this->soldItems($artist)
            ->groupBy('order_items.title', 'order_items.selling_type')
            ->selectRaw('order_items.title, order_items.selling_type, SUM(order_items.quantity) AS units, SUM(order_items.unit_artist_earning_amount * order_items.quantity) AS earnings')
            ->orderByDesc('units')
            ->limit($limit)
            ->get();
    }

    /**
     * Enabled products by print-check status, plus products switched off.
     *
     * @return array{pass: int, warn: int, fail: int, pending: int, disabled: int, total: int}
     */
    public function printReadiness(ArtistProfile $artist): array
    {
        $products = ArtistProduct::query()
            ->whereHas('artwork', fn (Builder $q) => $q->where('artist_profile_id', $artist->id)->where('status', '!=', 'archived'));

        $enabled = (clone $products)->where('is_enabled', true)->selectRaw('print_check_status, count(*) AS total')
            ->groupBy('print_check_status')->pluck('total', 'print_check_status');

        $counts = [
            'pass' => (int) ($enabled['pass'] ?? 0),
            'warn' => (int) ($enabled['warn'] ?? 0),
            'fail' => (int) ($enabled['fail'] ?? 0),
            'pending' => (int) ($enabled['pending'] ?? 0),
            'disabled' => (clone $products)->where('is_enabled', false)->count(),
        ];

        return $counts + ['total' => array_sum($counts)];
    }

    /**
     * Enabled products that are not print ready or only acceptable, worst first.
     *
     * @return Collection<int, ArtistProduct>
     */
    public function productsNeedingAttention(ArtistProfile $artist, int $limit = 6): Collection
    {
        return ArtistProduct::query()
            ->where('is_enabled', true)
            ->whereIn('print_check_status', ['fail', 'warn'])
            ->whereHas('artwork', fn (Builder $q) => $q->where('artist_profile_id', $artist->id)->whereNotIn('status', ['archived', 'suspended']))
            ->with(['artwork', 'product', 'placements'])
            ->orderByRaw("CASE print_check_status WHEN 'fail' THEN 0 ELSE 1 END")
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function recentSales(ArtistProfile $artist, int $limit = 6): Collection
    {
        return $this->soldItems($artist)
            ->with('order:id,number,paid_at,currency')
            ->orderByDesc(Order::select('paid_at')->whereColumn('orders.id', 'order_items.order_id'))
            ->limit($limit)
            ->get();
    }

    /**
     * Units sold per product in the period, for the sales-mix chart.
     *
     * @return Collection<int, OrderItem>
     */
    public function salesByProduct(ArtistProfile $artist, CarbonImmutable $from): Collection
    {
        return $this->soldItems($artist)
            ->whereHas('order', fn (Builder $q) => $q->where('paid_at', '>=', $from))
            ->selectRaw('product_name, SUM(quantity) AS units, SUM(unit_artist_earning_amount * quantity) AS earnings')
            ->groupBy('product_name')
            ->orderByDesc('units')
            ->get();
    }

    /**
     * Stock position of the artist's ready-made products (archived listings excluded).
     *
     * @return array{products: int, live: int, units: int, low: int, out: int, to_ship: int, value: int}
     */
    public function finishedInventory(ArtistProfile $artist): array
    {
        $variants = FinishedProductVariant::query()
            ->join('finished_products', 'finished_products.id', '=', 'finished_product_variants.finished_product_id')
            ->where('finished_products.artist_profile_id', $artist->id)
            ->where('finished_products.status', '!=', FinishedProductStatus::Archived->value)
            ->where('finished_product_variants.is_active', true);

        $units = (int) (clone $variants)->sum('finished_product_variants.stock_quantity');
        $low = (clone $variants)->where('finished_product_variants.stock_quantity', '>', 0)
            ->whereColumn('finished_product_variants.stock_quantity', '<=', 'finished_products.low_stock_threshold')->count();
        $out = (clone $variants)->where('finished_product_variants.stock_quantity', 0)->count();
        $value = (int) (clone $variants)->selectRaw('COALESCE(SUM(finished_product_variants.stock_quantity * COALESCE(finished_product_variants.price_amount, finished_products.price_amount)), 0) AS value')->value('value');

        $products = FinishedProduct::query()->where('artist_profile_id', $artist->id)->where('status', '!=', FinishedProductStatus::Archived->value);

        return [
            'products' => (clone $products)->count(),
            'live' => (clone $products)->where('status', FinishedProductStatus::Active->value)->count(),
            'units' => $units,
            'low' => $low,
            'out' => $out,
            'to_ship' => $this->itemsToShip($artist)->count(),
            'value' => $value,
        ];
    }

    /**
     * Paid ready-made items waiting for the artist to ship them.
     *
     * @return Builder<OrderItem>
     */
    public function itemsToShip(ArtistProfile $artist): Builder
    {
        return OrderItem::query()
            ->where('artist_profile_id', $artist->id)
            ->where('selling_type', SellingType::Finished->value)
            ->where('fulfillment_status', OrderItemStatus::AwaitingShipment->value);
    }

    /**
     * Shop set-up steps, each derived from real profile and catalogue data.
     *
     * @return list<array{label: string, done: bool, url: string, action: string}>
     */
    public function setupChecklist(ArtistProfile $artist): array
    {
        $designs = $artist->artworks()->where('status', '!=', 'archived');

        return [
            ['label' => __('Add a profile picture and bio'), 'done' => $artist->avatar_path !== null && filled($artist->bio), 'url' => route('studio.shop.edit'), 'action' => __('Edit shop')],
            ['label' => __('Upload your first design'), 'done' => (clone $designs)->exists(), 'url' => route('studio.artworks.create'), 'action' => __('Upload')],
            ['label' => __('Submit a design for review'), 'done' => (clone $designs)->whereNotNull('submitted_at')->exists(), 'url' => route('studio.artworks.index'), 'action' => __('Open designs')],
            ['label' => __('Get your account approved'), 'done' => $artist->isApproved(), 'url' => route('pages.show', 'artist-agreement'), 'action' => __('Read guidelines')],
            ['label' => __('Add a payout account'), 'done' => $artist->payoutAccounts()->exists(), 'url' => route('studio.earnings'), 'action' => __('Add account')],
            ['label' => __('Make your first sale'), 'done' => $this->soldItems($artist)->exists(), 'url' => route('artists.show', $artist), 'action' => __('Share shop')],
        ];
    }
}
