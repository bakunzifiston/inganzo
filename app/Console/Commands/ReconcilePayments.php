<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Providers may never deliver a callback (MTN sends exactly one, without retries), so
 * pending payments are actively re-checked against the provider and expired when stale.
 */
#[Signature('payments:reconcile {--older-than=2 : Only check payments pending for at least this many minutes}')]
#[Description('Query providers for pending payments and expire stale ones')]
class ReconcilePayments extends Command
{
    public function handle(PaymentService $payments): int
    {
        $checked = 0;

        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('provider', '!=', 'cash_on_delivery')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('older-than')))
            ->orderBy('id')
            ->chunkById(100, function ($pending) use ($payments, &$checked) {
                foreach ($pending as $payment) {
                    try {
                        $payments->refresh($payment);
                        $checked++;
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info("Checked {$checked} pending payments.");

        return self::SUCCESS;
    }
}
