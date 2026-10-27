<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\PayoutAccount;
use App\Models\PayoutRequest;
use App\Services\AuditLogger;
use App\Services\Finance\PayoutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public function __construct(private PayoutService $payouts) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(PayoutStatus::class)]]);

        return view('admin.payouts.index', [
            'payouts' => PayoutRequest::query()->with(['artist', 'payoutAccount', 'transactions'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->orderByRaw("CASE status WHEN 'requested' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END"))
                ->latest()->paginate(25)->withQueryString(),
            'unverified' => PayoutAccount::query()->whereNull('verified_at')->with('artistProfile')->get(),
        ]);
    }

    public function verifyAccount(Request $request, PayoutAccount $payoutAccount, AuditLogger $audit): RedirectResponse
    {
        $payoutAccount->forceFill(['verified_at' => now(), 'verified_by' => $request->user()->id])->save();
        $audit->log('payout_account.verified', $payoutAccount, ['artist_profile_id' => $payoutAccount->artist_profile_id]);

        return back()->with('success', __('Payout account verified.'));
    }

    public function approve(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $this->payouts->approve($payout, $request->user(), $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null);

        return back()->with('success', __('Payout approved. Send the transfer, then record its reference.'));
    }

    public function paid(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', Rule::enum(PayoutMethod::class)],
            'reference' => ['required', 'string', 'max:120', Rule::unique('payout_transactions', 'reference')->where('provider', $request->input('provider'))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->payouts->markPaid($payout, $request->user(), $validated['provider'], $validated['reference'], $validated['note'] ?? null);

        return back()->with('success', __('Payout :ref marked as paid.', ['ref' => $payout->reference]));
    }

    public function reject(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $this->payouts->reject($payout, $request->user(), $request->validate(['note' => ['required', 'string', 'max:500']])['note']);

        return back()->with('success', __('Payout rejected; the amount is back in the artist’s available balance.'));
    }

    public function failed(Request $request, PayoutRequest $payout): RedirectResponse
    {
        $this->payouts->markFailed($payout, $request->user(), $request->validate(['note' => ['required', 'string', 'max:500']])['note']);

        return back()->with('success', __('Payout marked as failed; the amount is back in the artist’s available balance.'));
    }
}
