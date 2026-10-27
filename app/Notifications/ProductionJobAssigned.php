<?php

namespace App\Notifications;

use App\Models\ProductionJob;

class ProductionJobAssigned extends MarketplaceNotification
{
    public function __construct(public ProductionJob $job) {}

    protected function subject(): string
    {
        return __('New production job :ref', ['ref' => $this->job->reference]);
    }

    protected function line(): string
    {
        return __('A new job is waiting for your facility. Due :date.', ['date' => $this->job->due_at->translatedFormat('j M Y')]);
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('partner.jobs.show', $this->job);
    }
}
