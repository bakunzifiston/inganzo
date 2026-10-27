<?php

namespace App\Http\Controllers\Auth;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\ArtistOnboarding;
use App\Services\Cart\CartManager;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', ['asArtist' => $request->query('as') === 'artist']);
    }

    public function store(Request $request, ArtistOnboarding $onboarding, CartManager $carts): RedirectResponse
    {
        $asArtist = $request->boolean('as_artist');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'display_name' => [$asArtist ? 'required' : 'nullable', 'string', 'min:2', 'max:80'],
            'accept_agreement' => [$asArtist ? 'accepted' : 'nullable'],
            'accept_terms' => ['accepted'],
        ]);

        $guestCart = $carts->guestToken();

        $user = DB::transaction(function () use ($validated, $asArtist, $onboarding) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'password' => $validated['password'],
                'locale' => app()->getLocale(),
            ]);
            $user->assignRole(RoleName::Customer);

            if ($asArtist) {
                $onboarding->start($user, $validated['display_name']);
            }

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        $carts->mergeGuestCart($user, $guestCart);

        return $asArtist
            ? redirect()->route('studio.dashboard')->with('success', __('Welcome! Your shop is ready. Upload your first design while our team reviews your account.'))
            : redirect()->route('verification.notice');
    }
}
