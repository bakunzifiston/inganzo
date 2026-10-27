<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('admin.settings.edit', ['settings' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        $validated = $request->validate([
            'payouts_hold_days' => ['required', 'integer', 'min:0', 'max:120'],
            'payouts_minimum_amount' => ['required', 'integer', 'min:0', 'max:100000000'],
            'checkout_payment_timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'checkout_cod_enabled' => ['sometimes', 'boolean'],
            'marketplace_finished_commission_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'marketplace_contact_email' => ['required', 'email'],
            'marketplace_contact_phone' => ['required', 'string', 'max:30'],
        ]);

        $actor = $request->user();
        $settings->set('payouts.hold_days', (int) $validated['payouts_hold_days'], $actor);
        $settings->set('payouts.minimum_amount', (int) $validated['payouts_minimum_amount'], $actor);
        $settings->set('checkout.payment_timeout_minutes', (int) $validated['checkout_payment_timeout_minutes'], $actor);
        $settings->set('checkout.cod_enabled', $request->boolean('checkout_cod_enabled'), $actor);
        $settings->set('marketplace.finished_commission_bps', (int) round($validated['marketplace_finished_commission_percent'] * 100), $actor);
        $settings->set('marketplace.contact_email', $validated['marketplace_contact_email'], $actor);
        $settings->set('marketplace.contact_phone', $validated['marketplace_contact_phone'], $actor);

        return back()->with('success', __('Settings saved. Changes are recorded in the audit log.'));
    }
}
