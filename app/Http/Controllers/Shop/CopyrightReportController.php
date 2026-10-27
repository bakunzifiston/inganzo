<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Artwork;
use App\Models\CopyrightReport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CopyrightReportController extends Controller
{
    public function create(?Artwork $artwork = null): View
    {
        return view('shop.copyright', ['artwork' => $artwork?->isPublic() ? $artwork : null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'artwork_url' => ['required', 'string', 'max:255'],
            'reporter_name' => ['required', 'string', 'max:120'],
            'reporter_email' => ['required', 'email', 'max:255'],
            'rights_holder' => ['required', 'string', 'max:160'],
            'original_work_url' => ['nullable', 'url', 'max:255'],
            'claim_details' => ['required', 'string', 'min:30', 'max:5000'],
            'good_faith_confirmed' => ['accepted'],
        ]);

        $slug = collect(explode('/', trim(parse_url($validated['artwork_url'], PHP_URL_PATH) ?? $validated['artwork_url'], '/')))
            ->skipUntil(fn ($segment) => $segment === 'designs')->skip(1)->first()
            ?? trim($validated['artwork_url'], '/');

        $artwork = Artwork::query()->where('slug', $slug)->first();

        if ($artwork === null) {
            return back()->withInput()->withErrors(['artwork_url' => __('We could not find that design. Paste the full link to the product page.')]);
        }

        CopyrightReport::query()->create([
            'artwork_id' => $artwork->id,
            'reporter_user_id' => $request->user()?->id,
            'reporter_name' => $validated['reporter_name'],
            'reporter_email' => $validated['reporter_email'],
            'rights_holder' => $validated['rights_holder'],
            'original_work_url' => $validated['original_work_url'] ?? null,
            'claim_details' => $validated['claim_details'],
            'good_faith_confirmed' => true,
        ]);

        return redirect()->route('home')->with('success', __('Thank you. Our team will review your report and contact you by e-mail.'));
    }
}
