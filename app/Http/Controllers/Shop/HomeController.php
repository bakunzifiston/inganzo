<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ArtCollection;
use App\Models\ArtistProfile;
use App\Models\Category;
use App\Models\ProductType;
use App\Models\Promotion;
use App\Services\Catalog\CatalogSearch;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public function __invoke(CatalogSearch $search): View
    {
        $trending = $search->query(['sort' => 'trending'])->limit(8)->get();

        return view('shop.home', [
            'heroItems' => collect(['hoodie' => 'hoodies-sweatshirts', 'tee' => 't-shirts', 'sticker' => 'stickers', 'mug' => 'mugs', 'tote' => 'tote-bags'])
                ->map(fn (string $type) => $search->query(['type' => $type, 'sort' => 'trending'])->first())
                ->filter(),
            'trending' => $trending,
            'readyMade' => $search->finishedQuery([])->with(['artist', 'images', 'variants'])->orderByDesc('finished_products.sales_count')->orderByDesc('finished_products.published_at')->limit(8)->get(),
            'newArrivals' => $search->query(['sort' => 'newest'])->limit(8)->get(),
            'tees' => $search->query(['type' => 't-shirts'])->limit(4)->get(),
            'hoodies' => $search->query(['type' => 'hoodies-sweatshirts'])->limit(4)->get(),
            'productTypes' => ProductType::query()->active()->orderBy('position')->get(),
            'promotions' => Promotion::query()->live()->get(),
            'categories' => Category::query()->active()->where('is_featured', true)->orderBy('position')->get(),
            'collections' => ArtCollection::query()->active()->whereNull('artist_profile_id')->where('is_featured', true)
                ->with(['artworks' => fn ($q) => $q->published()->with('currentVersion')->limit(4)])
                ->orderBy('position')->limit(3)->get(),
            'artists' => ArtistProfile::query()->approved()
                ->withCount('publishedArtworks')
                ->orderByDesc('is_featured')
                ->orderByDesc('published_artworks_count')
                ->limit(6)->get(),
        ]);
    }
}
