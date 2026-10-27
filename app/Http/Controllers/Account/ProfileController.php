<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Rules\ValidPhone;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $country = Country::query()->where('iso2', 'RW')->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', new ValidPhone($country)],
            'locale' => ['required', Rule::in(array_keys(config('marketplace.locales')))],
        ]);

        $request->user()->update([
            'name' => $validated['name'],
            'phone' => filled($validated['phone'] ?? null) ? PhoneNumber::normalize($validated['phone'], $country) : null,
            'locale' => $validated['locale'],
        ]);
        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('Profile updated.'));
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        return back()->with('success', __('Password changed.'));
    }

    public function notifications(Request $request): View
    {
        return view('account.notifications', [
            'notifications' => $request->user()->notifications()->paginate(20),
            'area' => $request->user()->isArtist() && str_contains((string) url()->previous(), '/studio') ? 'studio' : 'account',
        ]);
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('All notifications marked as read.'));
    }
}
