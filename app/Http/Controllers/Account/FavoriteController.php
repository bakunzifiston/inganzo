<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ArtistProduct;
use App\Models\Artwork;
use App\Services\Catalog\CatalogSearch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request): View
    {
        $artworkIds = $request->user()->favorites()->published()->pluck('artworks.id');

        // One representative product per favourited design.
        $items = ArtistProduct::query()
            ->listed()
            ->whereIn('artwork_id', $artworkIds)
            ->with(CatalogSearch::eagerLoads())
            ->orderByDesc('sales_count')
            ->get()
            ->unique('artwork_id')
            ->values();

        return view('account.favorites', ['items' => $items]);
    }

    public function toggle(Request $request, Artwork $artwork): RedirectResponse
    {
        abort_unless($artwork->isPublic(), 404);

        $result = $request->user()->favorites()->toggle($artwork->id);

        return back()->with('success', $result['attached'] !== []
            ? __('Saved to your favourites.')
            : __('Removed from your favourites.'));
    }
}
