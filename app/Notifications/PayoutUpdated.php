<?php

namespace App\Notifications;

use App\Models\PayoutRequest;
use App\Support\Money;

class PayoutUpdated extends MarketplaceNotification
{
    public function __construct(public PayoutRequest $payout) {}

    protected function subject(): string
    {
        return __('Payout :ref: :status', ['ref' => $this->payout->reference, 'status' => $this->payout->status->label()]);
    }

    protected function line(): string
    {
        return __('Your payout of :amount is now ":status".', [
            'amount' => Money::format($this->payout->amount, $this->payout->currency),
            'status' => $this->payout->status->label(),
        ]);
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('studio.earnings');
    }
}
