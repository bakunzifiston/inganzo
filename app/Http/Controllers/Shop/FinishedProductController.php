<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\FinishedProduct;
use App\Models\FinishedProductImage;
use App\Services\Catalog\CatalogSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront page and photos for ready-made products. Unlisted products (drafts, in review, taken
 * down) are visible only to their artist and moderators, through FinishedProductPolicy::view.
 */
class FinishedProductController extends Controller
{
    public function show(FinishedProduct $finishedProduct, CatalogSearch $search): View
    {
        Gate::authorize('view', $finishedProduct);

        $product = $finishedProduct->load(['artist', 'images', 'variants', 'category']);
        $listed = $product->isListed();
        if ($listed) {
            $product->increment('view_count');
        }

        $variants = $product->variants->where('is_active', true)->values()->map(fn ($variant) => [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'color' => $variant->color_name,
            'hex' => $variant->color_hex,
            'size' => $variant->size_label,
            'price' => $variant->currentPrice($product),
            'regular' => $variant->regularPrice($product),
            'stock' => $variant->stock_quantity,
        ]);

        $related = $search->finishedQuery(['category' => $product->category?->slug])
            ->where('finished_products.id', '!=', $product->id)
            ->with(['artist', 'images', 'variants'])
            ->orderByDesc('finished_products.sales_count')->limit(4)->get();
        $moreFromArtist = $search->finishedQuery(['artist' => $product->artist_profile_id])
            ->where('finished_products.id', '!=', $product->id)
            ->with(['artist', 'images', 'variants'])->limit(4)->get();

        return view('shop.finished', [
            'product' => $product,
            'variants' => $variants,
            'listed' => $listed,
            'related' => $related->reject(fn ($p) => $moreFromArtist->contains('id', $p->id))->values(),
            'moreFromArtist' => $moreFromArtist,
            'deliveryZones' => DeliveryZone::query()->where('is_active', true)->whereHas('country', fn ($q) => $q->where('iso2', 'RW'))->orderBy('position')->get(),
            'maxQuantity' => config('marketplace.cart_max_quantity'),
        ]);
    }

    public function image(FinishedProductImage $image, string $variant): Response
    {
        Gate::authorize('view', $image->product()->with('artist', 'variants')->firstOrFail());

        $path = $variant === 'thumbnail' ? $image->thumbnail_path : $image->preview_path;
        $disk = Storage::disk($image->disk);
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => $image->product->isListed() ? 'public, max-age=604800, immutable' : 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
