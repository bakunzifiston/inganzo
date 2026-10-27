<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Ability;
use App\Enums\ArtworkStatus;
use App\Enums\PaymentStatus;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Artwork;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\SupportTicket;
use App\Services\Reporting\AdminMetrics;
use App\Support\Navigation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * One dashboard for every staff role. Each section is only computed and shown when the
 * signed-in user holds the permission for that area, so a moderator sees review queues,
 * finance sees money, support sees tickets and orders, and administrators see everything.
 */
class DashboardController extends Controller
{
    public const RANGES = [7, 30, 90];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $days = in_array($request->integer('days'), self::RANGES, true) ? $request->integer('days') : 30;
        $metrics = AdminMetrics::forDays($days);
        $can = fn (Ability $ability) => $user->can($ability->value);

        $show = [
            'money' => $can(Ability::FinanceView),
            'orders' => $can(Ability::OrdersView),
            'platform' => $can(Ability::UsersView) || $can(Ability::ArtistsView),
            'moderation' => $can(Ability::ArtworksModerate),
            'support' => $can(Ability::SupportManage),
            'activity' => $can(Ability::AuditView) || $can(Ability::OrdersView),
        ];

        $summary = $metrics->summary();
        $previous = ($show['money'] || $show['orders']) ? $metrics->previous()->summary() : [];

        return view('admin.dashboard', [
            'user' => $user,
            'roleLabel' => Navigation::roleLabel($user),
            'days' => $days,
            'metrics' => $metrics,
            'show' => $show,
            'summary' => $summary,
            'deltas' => collect(['gmv', 'platform_revenue', 'completed_sales', 'orders_placed', 'new_artists'])
                ->mapWithKeys(fn ($key) => [$key => isset($previous[$key]) ? AdminMetrics::change($summary[$key], $previous[$key]) : null])->all(),
            'queues' => $this->queues($summary, $can),
            'trend' => $show['orders'] ? $metrics->trend() : null,
            'byType' => $show['orders'] ? $metrics->byProductType() : collect(),
            'topProducts' => $show['orders'] ? $metrics->topProducts() : collect(),
            'topArtists' => $show['orders'] || $show['platform'] ? $metrics->topArtists() : collect(),
            'platform' => $show['platform'] ? $metrics->platformCounts() : null,
            'revenueBreakdown' => $show['money'] ? $metrics->revenueBreakdown() : collect(),
            'liabilities' => $show['money'] ? $metrics->liabilities() : null,
            'pendingDesigns' => $show['moderation']
                ? Artwork::query()->where('status', ArtworkStatus::PendingReview)->with(['currentVersion', 'artist'])->oldest('submitted_at')->limit(6)->get()
                : collect(),
            'tickets' => $show['support']
                ? SupportTicket::query()->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Pending])->latest()->limit(6)->get()
                : collect(),
            'stalePayments' => $can(Ability::PaymentsView)
                ? Payment::query()->where('status', PaymentStatus::Pending)->where('created_at', '<', now()->subMinutes(30))->count()
                : null,
            'activity' => $show['activity'] ? $metrics->activity(10, $can(Ability::AuditView)) : collect(),
        ]);
    }

    /**
     * Work waiting for this user, limited to areas they may act on.
     *
     * @param  array<string, int>  $summary
     * @return list<array{label: string, hint: string, count: int, href: string, icon: string, tone: string}>
     */
    private function queues(array $summary, callable $can): array
    {
        return array_values(array_filter([
            $can(Ability::ArtworksModerate) ? ['label' => __('Designs to review'), 'hint' => __('Pending moderation'), 'count' => $summary['designs_awaiting'], 'href' => route('admin.moderation.index'), 'icon' => 'eye', 'tone' => 'clay'] : null,
            $can(Ability::ArtistsManage) ? ['label' => __('Artists to approve'), 'hint' => __('New shop applications'), 'count' => $summary['pending_artists'], 'href' => route('admin.artists.index', ['status' => 'pending']), 'icon' => 'palette', 'tone' => 'clay'] : null,
            $can(Ability::OrdersManage) ? ['label' => __('Items without a printer'), 'hint' => __('Assign a printing partner'), 'count' => $summary['unassigned'], 'href' => route('admin.orders.index', ['payment_status' => 'paid', 'fulfillment_status' => 'unfulfilled']), 'icon' => 'printer', 'tone' => 'gold'] : null,
            $can(Ability::OrdersManage) ? ['label' => __('Delayed production jobs'), 'hint' => __('Past their due date'), 'count' => $summary['delayed_jobs'], 'href' => route('admin.production.index'), 'icon' => 'clock', 'tone' => 'rose'] : null,
            $can(Ability::OrdersManage) ? ['label' => __('Ready to ship'), 'hint' => __('Passed quality control'), 'count' => $summary['awaiting_shipment'], 'href' => route('admin.production.index', ['status' => 'ready_for_dispatch']), 'icon' => 'truck', 'tone' => 'leaf'] : null,
            $can(Ability::RefundsManage) ? ['label' => __('Refunds to review'), 'hint' => __('Requested or approved'), 'count' => $summary['refunds_requested'], 'href' => route('admin.refunds.index'), 'icon' => 'refresh', 'tone' => 'rose'] : null,
            $can(Ability::PayoutsManage) ? ['label' => __('Artist payouts'), 'hint' => __('Requested or approved'), 'count' => PayoutRequest::query()->whereIn('status', ['requested', 'approved'])->count(), 'href' => route('admin.payouts.index'), 'icon' => 'wallet', 'tone' => 'gold'] : null,
            $can(Ability::ReportsManage) ? ['label' => __('Copyright reports'), 'hint' => __('Open or under review'), 'count' => $summary['open_copyright'], 'href' => route('admin.copyright.index'), 'icon' => 'flag', 'tone' => 'rose'] : null,
            $can(Ability::SupportManage) ? ['label' => __('Support tickets'), 'hint' => __('Open or waiting'), 'count' => SupportTicket::query()->whereIn('status', [SupportTicketStatus::Open, SupportTicketStatus::Pending])->count(), 'href' => route('admin.support.index'), 'icon' => 'mail', 'tone' => 'sky'] : null,
            $can(Ability::PaymentsView) ? ['label' => __('Orders awaiting payment'), 'hint' => __('Unpaid or pending'), 'count' => $summary['pending_payments'], 'href' => route('admin.payments.index', ['status' => 'pending']), 'icon' => 'phone', 'tone' => 'gold'] : null,
        ]));
    }
}
