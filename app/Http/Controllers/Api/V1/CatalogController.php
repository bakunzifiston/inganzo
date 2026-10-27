<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ArtistProductResource;
use App\Models\ArtistProduct;
use App\Models\Category;
use App\Models\ProductType;
use App\Services\Catalog\CatalogSearch;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request, CatalogSearch $search): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:50'],
            'size' => ['nullable', 'string', 'max:20'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', Rule::in(CatalogSearch::SORTS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        return ArtistProductResource::collection($search->paginate($filters, $filters['per_page'] ?? CatalogSearch::PER_PAGE));
    }

    public function show(ArtistProduct $artistProduct, PriceCalculator $pricing): ArtistProductResource
    {
        $artistProduct->load([...CatalogSearch::eagerLoads(), 'product.variants.color', 'product.variants.size']);
        abort_unless($artistProduct->isListed(), 404);

        $colorIds = $artistProduct->colors->pluck('id');
        $variants = $artistProduct->product->variants
            ->filter(fn ($v) => $v->is_active && $colorIds->contains($v->product_color_id))
            ->map(fn ($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'color' => $v->color->name,
                'size' => $v->size?->label,
                'price' => $pricing->quote($artistProduct, $v)->unitPrice,
            ])->values();

        return (new ArtistProductResource($artistProduct))->additional(['variants' => $variants]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()->active()->orderBy('position')->get()->map(fn ($c) => [
            'slug' => $c->slug, 'name' => $c->translate('name'), 'parent_id' => $c->parent_id,
        ])]);
    }

    public function productTypes(): JsonResponse
    {
        return response()->json(['data' => ProductType::query()->active()->orderBy('position')->get()->map(fn ($t) => [
            'slug' => $t->slug, 'name' => $t->translate('name'),
        ])]);
    }
}
