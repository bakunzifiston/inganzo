<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ArtistProduct;
use App\Models\Artwork;
use App\Models\DeliveryZone;
use App\Models\Product;
use App\Services\Catalog\CatalogSearch;
use App\Services\Pricing\PriceCalculator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    /**
     * A design page shows its default product.
     */
    public function artwork(Artwork $artwork): RedirectResponse
    {
        abort_unless($artwork->isPublic(), 404);

        $first = $artwork->artistProducts()->listed()->orderByDesc('sales_count')->with('product')->first();
        abort_if($first === null, 404);

        return redirect()->route('products.show', [$artwork, $first->product]);
    }

    public function show(Artwork $artwork, Product $product, PriceCalculator $pricing, CatalogSearch $search): View
    {
        $artistProduct = ArtistProduct::query()
            ->listed()
            ->where('artwork_id', $artwork->id)
            ->where('product_id', $product->id)
            ->with([...CatalogSearch::eagerLoads(), 'product.variants.color', 'product.variants.size', 'product.type', 'artwork.tags', 'artwork.category'])
            ->firstOrFail();

        $artwork->increment('view_count');

        $colorIds = $artistProduct->colors->pluck('id');
        $variants = $artistProduct->product->variants
            ->filter(fn ($variant) => $variant->is_active && $colorIds->contains($variant->product_color_id) && ($variant->size === null || $variant->size->is_active))
            ->sortBy(fn ($variant) => $variant->size?->position ?? 0)
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'color_id' => $variant->product_color_id,
                'size_id' => $variant->product_size_id,
                'size_label' => $variant->size?->label,
                'price' => $pricing->quote($artistProduct, $variant)->unitPrice,
            ])->values();

        $otherProducts = $artwork->artistProducts()->listed()->where('id', '!=', $artistProduct->id)->with(CatalogSearch::eagerLoads())->get();
        $related = $search->query(['category' => $artwork->category?->slug])
            ->where('artworks.id', '!=', $artwork->id)
            ->where('artist_products.product_id', $product->id)
            ->limit(4)->get();

        $reviews = $artistProduct->reviews()->where('status', 'published')->with('user')->latest()->limit(6)->get();

        return view('shop.product', [
            'artistProduct' => $artistProduct,
            'artwork' => $artistProduct->artwork,
            'product' => $artistProduct->product,
            'variants' => $variants,
            'otherProducts' => $otherProducts,
            'related' => $related,
            'reviews' => $reviews,
            'isFavorite' => auth()->check() && auth()->user()->favorites()->whereKey($artwork->id)->exists(),
            'deliveryZones' => DeliveryZone::query()->where('is_active', true)->whereHas('country', fn ($q) => $q->where('iso2', 'RW'))->orderBy('position')->get(),
        ]);
    }
}
