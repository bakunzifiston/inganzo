<?php

namespace App\Services\Reporting;

use App\Enums\ArtworkStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\OrderItemStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\RefundStatus;
use App\Models\ArtistProduct;
use App\Models\ArtistProfile;
use App\Models\Artwork;
use App\Models\AuditLog;
use App\Models\CopyrightReport;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PayoutRequest;
use App\Models\PrintingPartner;
use App\Models\Product;
use App\Models\ProductionJob;
use App\Models\Refund;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard KPIs. Every number is a database query over a stated period.
 *
 * "Sales" means orders whose payment_status is paid or partially_refunded (Order::countableSales);
 * pending, failed, cancelled and fully refunded orders are excluded. Sales are dated by paid_at.
 */
class AdminMetrics
{
    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to, public string $currency = 'RWF') {}

    public static function forDays(int $days): self
    {
        $to = CarbonImmutable::now();

        return new self($to->subDays($days - 1)->startOfDay(), $to);
    }

    /**
     * The same-length period immediately before this one, for comparisons.
     */
    public function previous(): self
    {
        $length = $this->from->diffInSeconds($this->to);

        return new self($this->from->subSeconds((int) $length + 1), $this->from->subSecond(), $this->currency);
    }

    /**
     * Percentage change from $before to $now; null when there is no baseline.
     */
    public static function change(int|float $now, int|float $before): ?float
    {
        return $before == 0 ? null : round(($now - $before) / abs($before) * 100, 1);
    }

    /**
     * Platform-wide totals for the overview (all time, plus new in period).
     *
     * @return array<string, int>
     */
    public function platformCounts(): array
    {
        $between = [$this->from, $this->to];

        return [
            'users' => User::query()->count(),
            'users_new' => User::query()->whereBetween('created_at', $between)->count(),
            'artists_approved' => ArtistProfile::query()->where('status', 'approved')->count(),
            'artists_pending' => ArtistProfile::query()->where('status', 'pending')->count(),
            'designs_published' => Artwork::query()->where('status', ArtworkStatus::Approved)->count(),
            'designs_total' => Artwork::query()->where('status', '!=', ArtworkStatus::Archived)->count(),
            'designs_new' => Artwork::query()->whereBetween('created_at', $between)->count(),
            'products_listed' => ArtistProduct::query()->listed()->count(),
            'blanks_active' => Product::query()->active()->count(),
            'orders_total' => Order::query()->count(),
            'partners_active' => PrintingPartner::query()->where('is_active', true)->count(),
            'print_failing' => ArtistProduct::query()->where('is_enabled', true)->where('print_check_status', 'fail')->count(),
        ];
    }

    /**
     * Ledger movements in the period grouped by entry type (platform revenue account).
     *
     * @return Collection<int, object{type: string, total: int}>
     */
    public function revenueBreakdown(): Collection
    {
        return LedgerEntry::query()
            ->where('account_type', LedgerAccount::Platform)->where('bucket', LedgerBucket::Revenue)
            ->where('currency', $this->currency)->whereBetween('created_at', [$this->from, $this->to])
            ->selectRaw('type, SUM(amount) AS total')->groupBy('type')->orderByDesc('total')->get();
    }

    /**
     * @return array{partner_payable: int, payouts_open: int, payouts_open_amount: int}
     */
    public function liabilities(): array
    {
        return [
            'partner_payable' => (int) LedgerEntry::query()->where('account_type', LedgerAccount::Partner)->where('currency', $this->currency)->sum('amount'),
            'payouts_open' => PayoutRequest::query()->whereIn('status', ['requested', 'approved'])->count(),
            'payouts_open_amount' => (int) PayoutRequest::query()->whereIn('status', ['requested', 'approved'])->sum('amount'),
        ];
    }

    /**
     * Recent platform events from the audit log, orders and registrations, newest first.
     *
     * @return Collection<int, array{icon: string, tone: string, text: string, time: CarbonInterface, url: string|null}>
     */
    public function activity(int $limit = 10, bool $includeAudit = true): Collection
    {
        $orders = Order::query()->latest('placed_at')->limit($limit)->get(['number', 'customer_name', 'total_amount', 'currency', 'payment_status', 'placed_at'])
            ->map(fn (Order $order) => [
                'icon' => 'receipt', 'tone' => $order->isPaid() ? 'leaf' : 'gold',
                'text' => __(':name placed order :number (:total)', ['name' => $order->customer_name, 'number' => $order->number, 'total' => Money::format($order->total_amount, $order->currency)]),
                'time' => $order->placed_at, 'url' => route('admin.orders.show', $order),
            ]);

        $artists = ArtistProfile::query()->latest()->limit($limit)->get(['display_name', 'slug', 'created_at'])
            ->map(fn (ArtistProfile $artist) => [
                'icon' => 'palette', 'tone' => 'clay',
                'text' => __(':name opened an artist shop', ['name' => $artist->display_name]),
                'time' => $artist->created_at, 'url' => route('admin.artists.show', $artist),
            ]);

        $audit = $includeAudit ? AuditLog::query()->with('user:id,name')->latest('created_at')->limit($limit)->get()
            ->map(fn (AuditLog $log) => [
                'icon' => 'shield', 'tone' => 'sky',
                'text' => __(':who: :action', ['who' => $log->user?->name ?? __('System'), 'action' => str_replace(['.', '_'], [' ', ' '], $log->action)]),
                'time' => $log->created_at, 'url' => null,
            ]) : collect();

        return $orders->concat($artists)->concat($audit)->sortByDesc(fn ($event) => $event['time'])->take($limit)->values();
    }

    private function sales()
    {
        return Order::query()->countableSales()->where('currency', $this->currency)->whereBetween('paid_at', [$this->from, $this->to]);
    }

    /**
     * @return array<string, int>
     */
    public function summary(): array
    {
        $sales = $this->sales();

        return [
            'orders_placed' => Order::query()->whereBetween('placed_at', [$this->from, $this->to])->count(),
            'completed_sales' => (clone $sales)->count(),
            'gmv' => (int) (clone $sales)->sum('total_amount'),
            'net_gmv' => (int) (clone $sales)->sum(DB::raw('total_amount - refunded_amount')),
            'platform_revenue' => (int) LedgerEntry::query()->where('account_type', LedgerAccount::Platform)->where('bucket', LedgerBucket::Revenue)
                ->where('currency', $this->currency)->whereBetween('created_at', [$this->from, $this->to])->sum('amount'),
            'artist_payable' => (int) LedgerEntry::query()->where('account_type', LedgerAccount::Artist)->whereIn('bucket', [LedgerBucket::Held, LedgerBucket::Available])
                ->where('currency', $this->currency)->sum('amount'),
            'artist_available' => (int) LedgerEntry::query()->where('account_type', LedgerAccount::Artist)->where('bucket', LedgerBucket::Available)
                ->where('currency', $this->currency)->sum('amount'),
            'pending_payments' => Order::query()->whereIn('payment_status', [OrderPaymentStatus::Pending, OrderPaymentStatus::Unpaid])->where('status', 'pending_payment')->count(),
            'awaiting_production' => OrderItem::query()->whereIn('fulfillment_status', [OrderItemStatus::AwaitingAssignment, OrderItemStatus::Assigned])->count(),
            'unassigned' => OrderItem::query()->where('fulfillment_status', OrderItemStatus::AwaitingAssignment)->count(),
            'delayed_jobs' => ProductionJob::query()->delayed()->count(),
            'awaiting_shipment' => OrderItem::query()->where('fulfillment_status', OrderItemStatus::Ready)->count(),
            'refunds_requested' => Refund::query()->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved])->count(),
            'refunded_amount' => (int) Refund::query()->where('status', RefundStatus::Processed)->whereBetween('processed_at', [$this->from, $this->to])->sum('amount'),
            'open_copyright' => CopyrightReport::query()->whereIn('status', ['open', 'under_review'])->count(),
            'new_artists' => ArtistProfile::query()->whereBetween('created_at', [$this->from, $this->to])->count(),
            'pending_artists' => ArtistProfile::query()->where('status', 'pending')->count(),
            'designs_awaiting' => Artwork::query()->where('status', ArtworkStatus::PendingReview)->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, gmv: list<int>, orders: list<int>}
     */
    public function trend(): array
    {
        $rows = $this->sales()
            ->selectRaw("to_char(date_trunc('day', paid_at), 'YYYY-MM-DD') AS day, SUM(total_amount) AS gmv, COUNT(*) AS orders")
            ->groupBy('day')->pluck('gmv', 'day');
        $counts = $this->sales()
            ->selectRaw("to_char(date_trunc('day', paid_at), 'YYYY-MM-DD') AS day, COUNT(*) AS orders")
            ->groupBy('day')->pluck('orders', 'day');

        $labels = $gmv = $orders = [];
        for ($day = $this->from; $day <= $this->to; $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('j M');
            $gmv[] = (int) ($rows[$key] ?? 0);
            $orders[] = (int) ($counts[$key] ?? 0);
        }

        return ['labels' => $labels, 'gmv' => $gmv, 'orders' => $orders];
    }

    private function salesItems()
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value])
            ->where('orders.currency', $this->currency)
            ->whereBetween('orders.paid_at', [$this->from, $this->to]);
    }

    /**
     * @return Collection<int, object>
     */
    public function byProductType(): Collection
    {
        return $this->salesItems()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('product_types', 'product_types.id', '=', 'products.product_type_id')
            ->selectRaw("product_types.name->>'en' AS label, SUM(order_items.line_total_amount - order_items.refunded_amount) AS revenue, SUM(order_items.quantity) AS units")
            ->groupBy('label')->orderByDesc('revenue')->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function byCountry(): Collection
    {
        return $this->sales()->join('countries', 'countries.id', '=', 'orders.country_id')
            ->selectRaw('countries.name AS label, COUNT(*) AS orders, SUM(orders.total_amount - orders.refunded_amount) AS revenue')
            ->groupBy('countries.name')->orderByDesc('revenue')->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function topProducts(int $limit = 5): Collection
    {
        return $this->salesItems()
            ->selectRaw('order_items.title, order_items.product_name, SUM(order_items.quantity) AS units, SUM(order_items.line_total_amount) AS revenue')
            ->groupBy('order_items.title', 'order_items.product_name')->orderByDesc('units')->limit($limit)->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function topArtists(int $limit = 5): Collection
    {
        return $this->salesItems()
            ->join('artist_profiles', 'artist_profiles.id', '=', 'order_items.artist_profile_id')
            ->selectRaw('artist_profiles.display_name, artist_profiles.slug, SUM(order_items.quantity) AS units, SUM(order_items.unit_artist_earning_amount * order_items.quantity) AS earnings')
            ->groupBy('artist_profiles.display_name', 'artist_profiles.slug')->orderByDesc('earnings')->limit($limit)->get();
    }
}
