<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:expire-unpaid {--hours=24}')]
#[Description('Cancel orders that were never paid and release their discount redemptions')]
class ExpireUnpaidOrders extends Command
{
    public function handle(CheckoutService $checkout): int
    {
        $count = 0;

        Order::query()
            ->where('status', OrderStatus::PendingPayment)
            ->where('payment_method', '!=', 'cash_on_delivery')
            ->where('placed_at', '<=', now()->subHours((int) $this->option('hours')))
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'pending')->where('expires_at', '>', now()))
            ->chunkById(100, function ($orders) use ($checkout, &$count) {
                foreach ($orders as $order) {
                    $checkout->cancelUnpaid($order, 'payment_not_received');
                    $count++;
                }
            });

        $this->info("Cancelled {$count} unpaid orders.");

        return self::SUCCESS;
    }
}
