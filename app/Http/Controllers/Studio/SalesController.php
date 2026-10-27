<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Reporting\ArtistMetrics;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Line-level sales for the signed-in artist only. Customer contact details are never shown.
 */
class SalesController extends Controller
{
    public function __invoke(Request $request, ArtistMetrics $metrics): View
    {
        $artist = $request->user()->artistProfile;

        $items = $metrics->soldItems($artist)
            ->with(['order:id,number,paid_at,currency,payment_status,country_id', 'order.country:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'ilike', '%'.$request->string('q').'%'))
            ->orderByDesc(Order::select('paid_at')->whereColumn('orders.id', 'order_items.order_id'))
            ->paginate(25)
            ->withQueryString();

        return view('studio.sales', [
            'items' => $items,
            'totals' => [
                'units' => (int) $metrics->soldItems($artist)->sum('quantity'),
                'earnings' => (int) $metrics->soldItems($artist)->sum(DB::raw('unit_artist_earning_amount * quantity')),
            ],
        ]);
    }
}
