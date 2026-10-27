<?php

namespace App\Http\Controllers\Studio;

use App\Enums\ArtworkStatus;
use App\Enums\FinishedProductStatus;
use App\Http\Controllers\Controller;
use App\Services\Finance\PayoutService;
use App\Services\Reporting\AdminMetrics;
use App\Services\Reporting\ArtistMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ArtistMetrics $metrics, PayoutService $payouts): View
    {
        $artist = $request->user()->artistProfile;
        $days = in_array($request->integer('days'), [7, 30, 90], true) ? $request->integer('days') : 30;
        $from = CarbonImmutable::now()->subDays($days - 1)->startOfDay();

        $summary = $metrics->summary($artist, $from);
        $previous = $metrics->summary($artist, $from->subDays($days), $from->subSecond());

        return view('studio.dashboard', [
            'artist' => $artist,
            'days' => $days,
            'summary' => $summary,
            'deltas' => [
                'units' => AdminMetrics::change($summary['units'], $previous['units']),
                'earnings' => AdminMetrics::change($summary['earnings'], $previous['earnings']),
                'orders' => AdminMetrics::change($summary['orders'], $previous['orders']),
            ],
            'payout' => $payouts->eligibility($artist),
            'checklist' => $metrics->setupChecklist($artist),
            'latestDesigns' => $artist->artworks()->where('status', '!=', 'archived')->with(['currentVersion'])
                ->withCount(['artistProducts as live_products_count' => fn ($q) => $q->listed()])
                ->latest('updated_at')->limit(6)->get(),
            'inventory' => $inventory = $metrics->finishedInventory($artist),
            'stockAlerts' => $inventory['products'] === 0 ? collect() : $artist->finishedProducts()->whereIn('status', [FinishedProductStatus::Active->value, FinishedProductStatus::PendingReview->value])
                ->with('variants')->get()->filter(fn ($p) => ! $p->isInStock() || $p->isLowStock())->sortBy(fn ($p) => $p->totalStock())->take(5)->values(),
            'readiness' => $metrics->printReadiness($artist),
            'attention' => $metrics->productsNeedingAttention($artist),
            'recentSales' => $metrics->recentSales($artist),
            'salesByProduct' => $metrics->salesByProduct($artist, $from),
            'trend' => $metrics->trend($artist, $from),
            'topDesigns' => $metrics->topDesigns($artist),
            'statusCounts' => $artist->artworks()->selectRaw('status, count(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'needsAttention' => $artist->artworks()->whereIn('status', [ArtworkStatus::Rejected, ArtworkStatus::Draft])->with('moderationReviews')->latest('updated_at')->limit(5)->get(),
            'notifications' => $request->user()->unreadNotifications()->limit(5)->get(),
        ]);
    }
}
