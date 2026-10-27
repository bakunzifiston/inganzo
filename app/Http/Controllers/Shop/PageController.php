<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public const PAGES = ['terms', 'privacy', 'refunds', 'artist-agreement', 'delivery', 'payments', 'sell', 'about'];

    public function __invoke(string $page): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view('pages.'.$page);
    }
}
