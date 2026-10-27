<?php

namespace App\Http\Controllers\Account;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('account.dashboard', [
            'user' => $user,
            // Orders still on their way: paid (or cash on delivery) and not yet completed or cancelled.
            'activeOrders' => $user->orders()
                ->where('status', OrderStatus::Processing)
                ->with(['items', 'shipments'])
                ->latest('placed_at')->limit(3)->get(),
            'awaitingPayment' => $user->orders()->where('status', OrderStatus::PendingPayment)->latest('placed_at')->first(),
            'recentOrders' => $user->orders()->with('items')->latest('placed_at')->limit(5)->get(),
            'orderCount' => $user->orders()->count(),
            'totalSpent' => (int) $user->orders()->countableSales()->sum(DB::raw('total_amount - refunded_amount')),
            'favorites' => $user->favorites()->published()->with('currentVersion')->latest('favorites.created_at')->limit(4)->get(),
            'favoriteCount' => $user->favorites()->count(),
            'addressCount' => $user->addresses()->count(),
            'defaultAddress' => $user->addresses()->orderByDesc('is_default')->with('district')->first(),
            'unreadCount' => $user->unreadNotifications()->count(),
            'spending' => $this->monthlySpending($user),
        ]);
    }

    /**
     * Paid spend (after refunds) per month for the last six months.
     *
     * @return array{labels: list<string>, totals: list<int>}
     */
    private function monthlySpending($user): array
    {
        $from = now()->startOfMonth()->subMonths(5);
        $rows = $user->orders()->countableSales()->where('paid_at', '>=', $from)
            ->selectRaw("to_char(paid_at AT TIME ZONE ?, 'YYYY-MM') AS month, SUM(total_amount - refunded_amount) AS total", [config('app.timezone')])
            ->groupBy('month')->pluck('total', 'month');

        $labels = $totals = [];
        for ($month = $from->copy(); $month <= now(); $month->addMonth()) {
            $labels[] = $month->translatedFormat('M Y');
            $totals[] = (int) ($rows[$month->format('Y-m')] ?? 0);
        }

        return ['labels' => $labels, 'totals' => $totals];
    }
}
