<?php

namespace App\Http\Controllers\Studio;

use App\Enums\SellingType;
use App\Http\Controllers\Controller;
use App\Models\Artwork;
use App\Models\FinishedProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One catalogue for both selling methods: printed designs and ready-made products of the signed-in
 * artist, with filters for type, status and stock. Rows are combined with a UNION on a common set of
 * columns, paginated, then hydrated into models for display.
 */
class ProductsController extends Controller
{
    private const STATUSES = ['live', 'draft', 'review', 'changes', 'taken_down', 'archived'];

    public function __invoke(Request $request): View
    {
        $artist = $request->user()->artistProfile;
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $stock = $request->string('stock')->toString();
        $term = trim($request->string('q')->toString());

        $designs = Artwork::query()->toBase()
            ->where('artworks.artist_profile_id', $artist->id)
            ->selectRaw("'print_on_demand' AS kind, artworks.id, artworks.title, artworks.updated_at, NULL::int AS stock, NULL::int AS threshold")
            ->selectRaw("CASE artworks.status WHEN 'approved' THEN 'live' WHEN 'pending_review' THEN 'review' WHEN 'rejected' THEN 'changes' WHEN 'suspended' THEN 'taken_down' ELSE artworks.status END AS status_key");

        $finished = FinishedProduct::query()->toBase()
            ->where('finished_products.artist_profile_id', $artist->id)
            ->selectRaw("'finished' AS kind, finished_products.id, finished_products.title, finished_products.updated_at")
            ->selectRaw('(SELECT COALESCE(SUM(v.stock_quantity), 0) FROM finished_product_variants v WHERE v.finished_product_id = finished_products.id AND v.is_active)::int AS stock, finished_products.low_stock_threshold AS threshold')
            ->selectRaw("CASE finished_products.status WHEN 'active' THEN 'live' WHEN 'pending_review' THEN 'review' WHEN 'rejected' THEN 'changes' WHEN 'suspended' THEN 'taken_down' ELSE finished_products.status END AS status_key");

        $combined = DB::query()->fromSub($designs->unionAll($finished), 'p');

        $counts = (clone $combined)->selectRaw('kind, count(*) AS total')->groupBy('kind')->pluck('total', 'kind');
        $stockCounts = [
            'in' => (clone $combined)->where('kind', 'finished')->where('stock', '>', 0)->count(),
            'low' => (clone $combined)->where('kind', 'finished')->where('stock', '>', 0)->whereColumn('stock', '<=', 'threshold')->count(),
            'out' => (clone $combined)->where('kind', 'finished')->where('stock', 0)->count(),
        ];

        $rows = (clone $combined)
            ->when(in_array($type, [SellingType::PrintOnDemand->value, SellingType::Finished->value], true), fn ($q) => $q->where('kind', $type))
            ->when(in_array($status, self::STATUSES, true), fn ($q) => $q->where('status_key', $status))
            ->when($term !== '', fn ($q) => $q->where('title', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%'))
            ->when($stock === 'in', fn ($q) => $q->where('kind', 'finished')->where('stock', '>', 0))
            ->when($stock === 'low', fn ($q) => $q->where('kind', 'finished')->where('stock', '>', 0)->whereColumn('stock', '<=', 'threshold'))
            ->when($stock === 'out', fn ($q) => $q->where('kind', 'finished')->where('stock', 0))
            ->orderByDesc('updated_at')->orderBy('kind')->orderByDesc('id')
            ->paginate(18)
            ->withQueryString();

        $artworks = Artwork::query()->whereIn('id', $rows->where('kind', 'print_on_demand')->pluck('id'))
            ->with('currentVersion')->withCount(['artistProducts as enabled_products_count' => fn ($q) => $q->where('is_enabled', true)])->get()->keyBy('id');
        $products = FinishedProduct::query()->whereIn('id', $rows->where('kind', 'finished')->pluck('id'))
            ->with(['images', 'variants'])->get()->keyBy('id');

        $rows->setCollection($rows->getCollection()->map(fn ($row) => (object) [
            'type' => SellingType::from($row->kind),
            'model' => $row->kind === 'finished' ? $products->get($row->id) : $artworks->get($row->id),
        ])->filter(fn ($row) => $row->model !== null)->values());

        return view('studio.products', [
            'rows' => $rows,
            'filters' => ['type' => $type, 'status' => $status, 'stock' => $stock, 'q' => $term],
            'counts' => $counts,
            'stockCounts' => $stockCounts,
        ]);
    }
}
