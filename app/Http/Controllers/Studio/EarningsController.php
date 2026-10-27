<?php

namespace App\Http\Controllers\Studio;

use App\Enums\PayoutMethod;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\PayoutAccount;
use App\Services\AuditLogger;
use App\Services\Finance\PayoutService;
use App\Services\Settings;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EarningsController extends Controller
{
    public function index(Request $request, PayoutService $payouts): View
    {
        $artist = $request->user()->artistProfile;

        return view('studio.earnings', [
            'artist' => $artist,
            'eligibility' => $payouts->eligibility($artist),
            'held' => (int) $artist->ledgerEntries()->where('bucket', 'held')->sum('amount'),
            'entries' => $artist->ledgerEntries()->with('order:id,number')->latest('id')->paginate(20),
            'payoutRequests' => $artist->payoutRequests()->latest()->limit(10)->get(),
            'account' => $artist->defaultPayoutAccount,
            'holdDays' => app(Settings::class)->integer('payouts.hold_days'),
        ]);
    }

    public function requestPayout(Request $request, PayoutService $payouts): RedirectResponse
    {
        $artist = $request->user()->artistProfile;
        $currency = $payouts->eligibility($artist)['currency'];
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);

        $payout = $payouts->request($artist, Money::toMinor((string) $validated['amount'], $currency));

        return back()->with('success', __('Payout :ref requested. We will notify you when it is processed.', ['ref' => $payout->reference]));
    }

    public function updateAccount(Request $request): RedirectResponse
    {
        $artist = $request->user()->artistProfile;
        $country = Country::query()->where('iso2', 'RW')->firstOrFail();

        $validated = $request->validate([
            'method' => ['required', Rule::enum(PayoutMethod::class)],
            'account_name' => ['required', 'string', 'max:120'],
            'account_number' => ['required', 'string', 'max:40'],
            'bank_name' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:120'],
        ]);

        $number = $validated['account_number'];
        if ($validated['method'] !== PayoutMethod::BankTransfer->value) {
            $number = PhoneNumber::normalize($number, $country)
                ?? throw ValidationException::withMessages(['account_number' => __('Enter the mobile money number, for example 078 123 4567.')]);
        } elseif (! preg_match('/^[0-9\- ]{6,40}$/', $number)) {
            throw ValidationException::withMessages(['account_number' => __('Enter a valid bank account number.')]);
        }

        if ($artist->payoutRequests()->whereIn('status', ['requested', 'approved'])->exists()) {
            throw ValidationException::withMessages(['account_number' => __('You cannot change your payout account while a payout is in progress.')]);
        }

        DB::transaction(function () use ($artist, $validated, $number, $country) {
            $artist->payoutAccounts()->update(['is_default' => false]);
            PayoutAccount::query()->create([
                'artist_profile_id' => $artist->id,
                'method' => $validated['method'],
                'account_name' => $validated['account_name'],
                'account_number' => $number,
                'account_last4' => substr(preg_replace('/\D/', '', $number), -4),
                'bank_name' => $validated['bank_name'] ?? null,
                'country_id' => $country->id,
                'is_default' => true,
            ]);
        });

        app(AuditLogger::class)->log('payout_account.changed', $artist, ['method' => $validated['method']]);

        return back()->with('success', __('Payout account saved. Our finance team will verify it before your next payout.'));
    }
}
