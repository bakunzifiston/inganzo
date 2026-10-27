<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\Accounts\ArtistOnboarding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BecomeArtistController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->isArtist()) {
            return redirect()->route('studio.dashboard');
        }

        return view('account.become-artist', ['user' => $request->user()]);
    }

    public function store(Request $request, ArtistOnboarding $onboarding): RedirectResponse
    {
        if ($request->user()->isArtist()) {
            return redirect()->route('studio.dashboard');
        }

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:80'],
            'accept_agreement' => ['accepted'],
        ]);

        $onboarding->start($request->user(), $validated['display_name']);

        return redirect()->route('studio.dashboard')
            ->with('success', __('Welcome to your studio! Upload your first design while our team reviews your shop.'));
    }
}
