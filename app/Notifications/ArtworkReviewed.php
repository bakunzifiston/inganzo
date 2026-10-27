<?php

namespace App\Notifications;

use App\Enums\ModerationDecision;
use App\Models\Artwork;

class ArtworkReviewed extends MarketplaceNotification
{
    public function __construct(public Artwork $artwork, public ModerationDecision $decision, public ?string $notes) {}

    protected function subject(): string
    {
        return match ($this->decision) {
            ModerationDecision::Approved => __('":title" is now live', ['title' => $this->artwork->title]),
            ModerationDecision::Suspended => __('":title" has been taken down', ['title' => $this->artwork->title]),
            default => __('Changes requested for ":title"', ['title' => $this->artwork->title]),
        };
    }

    protected function line(): string
    {
        return match ($this->decision) {
            ModerationDecision::Approved => __('Your design passed review and is now available in your shop.'),
            default => __('Reviewer notes: :notes', ['notes' => $this->notes ?: '—']),
        };
    }

    protected function actionUrl(object $notifiable): string
    {
        return route('studio.artworks.show', $this->artwork);
    }
}
