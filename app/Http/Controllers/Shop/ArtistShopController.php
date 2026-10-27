<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Models\ProductType;
use App\Services\Catalog\CatalogSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ArtistShopController extends Controller
{
    public function index(Request $request): View
    {
        $artists = ArtistProfile::query()
            ->approved()
            ->whereHas('publishedArtworks')
            ->withCount('publishedArtworks')
            ->when($request->filled('q'), fn ($q) => $q->where('display_name', 'ilike', '%'.$request->string('q').'%'))
            ->orderByDesc('is_featured')
            ->orderBy('display_name')
            ->paginate(24)
            ->withQueryString();

        return view('shop.artists', ['artists' => $artists]);
    }

    public function show(ArtistProfile $artistProfile, CatalogSearch $search, Request $request): View
    {
        abort_unless($artistProfile->isApproved(), 404);

        $type = $request->string('type')->toString() ?: null;

        return view('shop.artist', [
            'artist' => $artistProfile->loadCount('publishedArtworks'),
            'products' => $search->paginateMixed(['artist' => $artistProfile->id, 'type' => $type, 'sort' => $request->string('sort')->toString() ?: 'trending']),
            'bestSellers' => $search->query(['artist' => $artistProfile->id, 'sort' => 'trending'])->where('artist_products.sales_count', '>', 0)->limit(4)->get(),
            'collections' => $artistProfile->collections()->where('is_active', true)->withCount('artworks')->get(),
            'types' => ProductType::query()->active()->orderBy('position')->get(),
            'activeType' => $type,
        ]);
    }
}
