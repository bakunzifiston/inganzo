<?php

namespace App\Services\Finance;

use App\Enums\ArtistStatus;
use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Enums\PayoutStatus;
use App\Models\ArtistProfile;
use App\Models\PayoutRequest;
use App\Models\PayoutTransaction;
use App\Models\User;
use App\Notifications\PayoutUpdated;
use App\Services\AuditLogger;
use App\Services\Settings;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Artist payouts. Requesting a payout immediately reserves the amount from the
 * available balance (a negative ledger entry); rejection or failure reverses it.
 */
class PayoutService
{
    public function __construct(
        private Ledger $ledger,
        private Settings $settings,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{eligible: bool, available: int, minimum: int, currency: string, reasons: list<string>}
     */
    public function eligibility(ArtistProfile $artist): array
    {
        $currency = $this->settings->get('payouts.currency');
        $available = $this->ledger->artistBalance($artist->id, LedgerBucket::Available, $currency);
        $minimum = $this->settings->integer('payouts.minimum_amount');
        $account = $artist->defaultPayoutAccount;
        $reasons = [];

        if ($artist->status !== ArtistStatus::Approved) {
            $reasons[] = __('Your artist account must be approved.');
        }
        if ($account === null) {
            $reasons[] = __('Add a payout account.');
        } elseif (! $account->isVerified()) {
            $reasons[] = __('Your payout account is waiting for verification by our team.');
        }
        if ($available < $minimum) {
            $reasons[] = __('Your available balance must be at least :min.', ['min' => Money::format($minimum, $currency)]);
        }
        if ($artist->payoutRequests()->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])->exists()) {
            $reasons[] = __('You already have a payout in progress.');
        }

        return ['eligible' => $reasons === [], 'available' => $available, 'minimum' => $minimum, 'currency' => $currency, 'reasons' => $reasons];
    }

    public function request(ArtistProfile $artist, int $amount): PayoutRequest
    {
        $payout = DB::transaction(function () use ($artist, $amount) {
            // Serialise payout requests per artist so two requests cannot spend the same balance.
            $artist = ArtistProfile::query()->lockForUpdate()->findOrFail($artist->id);
            $check = $this->eligibility($artist);

            if (! $check['eligible']) {
                throw ValidationException::withMessages(['amount' => $check['reasons']]);
            }
            if ($amount < $check['minimum'] || $amount > $check['available']) {
                throw ValidationException::withMessages(['amount' => __('Request between :min and :max.', [
                    'min' => Money::format($check['minimum'], $check['currency']),
                    'max' => Money::format($check['available'], $check['currency']),
                ])]);
            }

            $account = $artist->defaultPayoutAccount;
            $payout = PayoutRequest::query()->create([
                'reference' => Reference::generate('PO', PayoutRequest::class),
                'artist_profile_id' => $artist->id,
                'payout_account_id' => $account->id,
                'amount' => $amount,
                'currency' => $check['currency'],
                'status' => PayoutStatus::Requested,
                'method' => $account->method,
                'account_display' => $account->maskedDisplay(),
            ]);

            $this->ledger->post([
                $this->ledger->entry("payout:{$payout->id}", LedgerAccount::Artist, $artist->id, LedgerBucket::Available, LedgerEntryType::Payout, -$amount, $payout->currency, "{$payout->reference} payout requested", ['payout_request_id' => $payout->id]),
            ]);

            return $payout;
        });

        $this->audit->log('payout.requested', $payout, ['amount' => $amount]);

        return $payout;
    }

    public function approve(PayoutRequest $payout, User $reviewer, ?string $note = null): void
    {
        $this->transition($payout, [PayoutStatus::Requested], PayoutStatus::Approved, $reviewer, $note);
    }

    public function markPaid(PayoutRequest $payout, User $actor, string $provider, string $reference, ?string $note = null): void
    {
        DB::transaction(function () use ($payout, $actor, $provider, $reference, $note) {
            $payout = PayoutRequest::query()->lockForUpdate()->findOrFail($payout->id);
            $this->assertStatus($payout, [PayoutStatus::Approved]);

            PayoutTransaction::query()->create([
                'payout_request_id' => $payout->id,
                'provider' => $provider,
                'reference' => $reference,
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'status' => 'succeeded',
                'notes' => $note,
                'executed_by' => $actor->id,
                'executed_at' => now(),
            ]);

            $payout->forceFill(['status' => PayoutStatus::Paid, 'paid_at' => now(), 'admin_note' => $note ?? $payout->admin_note])->save();
            $this->audit->log('payout.paid', $payout, ['reference' => $reference, 'amount' => $payout->amount], $actor);
        });

        $payout->artist->user->notify(new PayoutUpdated($payout->fresh()));
    }

    public function reject(PayoutRequest $payout, User $reviewer, string $note): void
    {
        $this->reverse($payout, [PayoutStatus::Requested, PayoutStatus::Approved], PayoutStatus::Rejected, $reviewer, $note);
    }

    public function markFailed(PayoutRequest $payout, User $actor, string $note): void
    {
        $this->reverse($payout, [PayoutStatus::Approved], PayoutStatus::Failed, $actor, $note);
    }

    /**
     * @param  list<PayoutStatus>  $from
     */
    private function transition(PayoutRequest $payout, array $from, PayoutStatus $to, User $actor, ?string $note): void
    {
        DB::transaction(function () use ($payout, $from, $to, $actor, $note) {
            $payout = PayoutRequest::query()->lockForUpdate()->findOrFail($payout->id);
            $this->assertStatus($payout, $from);
            $payout->forceFill(['status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'admin_note' => $note])->save();
            $this->audit->log('payout.'.$to->value, $payout, ['note' => $note], $actor);
        });

        $payout->artist->user->notify(new PayoutUpdated($payout->fresh()));
    }

    /**
     * @param  list<PayoutStatus>  $from
     */
    private function reverse(PayoutRequest $payout, array $from, PayoutStatus $to, User $actor, string $note): void
    {
        DB::transaction(function () use ($payout, $from, $to, $actor, $note) {
            $payout = PayoutRequest::query()->lockForUpdate()->findOrFail($payout->id);
            $this->assertStatus($payout, $from);

            $this->ledger->post([
                $this->ledger->entry("payout:{$payout->id}:reversal", LedgerAccount::Artist, $payout->artist_profile_id, LedgerBucket::Available, LedgerEntryType::PayoutReversal, $payout->amount, $payout->currency, "{$payout->reference} payout {$to->value}", ['payout_request_id' => $payout->id, 'created_by' => $actor->id]),
            ]);

            $payout->forceFill(['status' => $to, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'admin_note' => $note])->save();
            $this->audit->log('payout.'.$to->value, $payout, ['note' => $note], $actor);
        });

        $payout->artist->user->notify(new PayoutUpdated($payout->fresh()));
    }

    /**
     * @param  list<PayoutStatus>  $allowed
     */
    private function assertStatus(PayoutRequest $payout, array $allowed): void
    {
        if (! in_array($payout->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('This payout is already :status.', ['status' => $payout->status->label()])]);
        }
    }
}
