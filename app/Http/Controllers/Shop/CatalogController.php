<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ArtCollection;
use App\Models\Category;
use Illuminate\Contracts\View\View;

class CatalogController extends Controller
{
    public function index(): View
    {
        return view('shop.catalog', [
            'heading' => request('q') ? __('Results for “:q”', ['q' => request('q')]) : __('Shop all designs'),
            'category' => null,
            'collection' => null,
        ]);
    }

    public function category(Category $category): View
    {
        abort_unless($category->is_active, 404);

        return view('shop.catalog', ['heading' => $category->translate('name'), 'category' => $category, 'collection' => null]);
    }

    public function collection(ArtCollection $collection): View
    {
        abort_unless($collection->is_active, 404);

        return view('shop.catalog', ['heading' => $collection->translate('title'), 'category' => null, 'collection' => $collection]);
    }
}
