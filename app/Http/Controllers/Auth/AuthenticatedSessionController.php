<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Cart\CartManager;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, CartManager $carts): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guestCart = $carts->guestToken();

        if (! Auth::attempt(['email' => mb_strtolower($credentials['email']), 'password' => $credentials['password']], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuspended()) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => __('Your account has been suspended. Contact support for help.')]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $carts->mergeGuestCart($user, $guestCart);

        $home = match (true) {
            $user->can('admin.access') => route('admin.dashboard'),
            $user->isPartnerStaff() => route('partner.dashboard'),
            $user->isArtist() => route('studio.dashboard'),
            default => route('account.dashboard'),
        };

        // Only return to the page the user was heading to if this account may open it,
        // e.g. not an artist-studio link after switching to an administrator account.
        $intended = (string) $request->session()->pull('url.intended', '');
        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $allowed = match (true) {
            $intended === '' => false,
            str_starts_with($path, '/studio') => $user->isArtist(),
            str_starts_with($path, '/partner') => $user->isPartnerStaff(),
            str_starts_with($path, '/admin') => $user->can('admin.access'),
            default => true,
        };

        return redirect()->to($allowed ? $intended : $home);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
