<?php

namespace App\Services\Artwork;

use App\Enums\ArtistStatus;
use App\Enums\ArtworkStatus;
use App\Enums\ModerationDecision;
use App\Enums\PrintCheckStatus;
use App\Models\Artwork;
use App\Models\User;
use App\Notifications\ArtworkReviewed;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Artwork review lifecycle: draft → pending_review → approved | rejected; approved → suspended (takedown).
 * Unapproved artwork is never shown publicly.
 */
class ArtworkModeration
{
    public function __construct(private AuditLogger $audit) {}

    public function submit(Artwork $artwork, User $artist): void
    {
        $artwork->loadMissing(['currentVersion', 'artist', 'artistProducts']);

        $problems = [];
        if (! in_array($artwork->status, [ArtworkStatus::Draft, ArtworkStatus::Rejected], true)) {
            $problems[] = __('Only drafts or designs with requested changes can be submitted.');
        }
        if ($artwork->artist->status === ArtistStatus::Suspended || $artwork->artist->status === ArtistStatus::Rejected) {
            $problems[] = __('Your artist account cannot submit designs right now.');
        }
        if (! $artwork->currentVersion?->isReady()) {
            $problems[] = __('Wait for your artwork file to finish processing.');
        }
        if ($artwork->rights_confirmed_at === null) {
            $problems[] = __('Confirm that you own the rights to this artwork.');
        }

        $artwork->loadMissing('artistProducts.product', 'artistProducts.placements');
        $enabled = $artwork->artistProducts->where('is_enabled', true);
        $sellable = $enabled->filter(fn ($p) => in_array($p->print_check_status, [PrintCheckStatus::Pass, PrintCheckStatus::Warn], true));
        if ($sellable->isEmpty()) {
            $problems[] = __('Enable at least one product that passes the print check.');
        }

        // Every product offered for sale must be print-ready; failing ones must be resized or disabled first.
        foreach ($enabled->reject(fn ($p) => $sellable->contains($p)) as $product) {
            $problems[] = __(':product is not print-ready (:dpi DPI). Use “Best print size” in the editor or turn the product off.', [
                'product' => $product->product->translate('name'),
                'dpi' => (int) $product->placements->min('effective_dpi'),
            ]);
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['submit' => $problems]);
        }

        DB::transaction(function () use ($artwork, $artist) {
            $artwork->forceFill(['status' => ArtworkStatus::PendingReview, 'submitted_at' => now()])->save();
            $this->record($artwork, $artist, ModerationDecision::Submitted, null);
        });
    }

    public function approve(Artwork $artwork, User $reviewer, ?string $notes = null): void
    {
        $this->assertStatus($artwork, [ArtworkStatus::PendingReview]);

        DB::transaction(function () use ($artwork, $reviewer, $notes) {
            $artwork->forceFill(['status' => ArtworkStatus::Approved, 'published_at' => $artwork->published_at ?? now()])->save();
            $this->record($artwork, $reviewer, ModerationDecision::Approved, $notes);
            $this->audit->log('artwork.approved', $artwork, ['notes' => $notes], $reviewer);
        });

        $artwork->artist->user->notify(new ArtworkReviewed($artwork, ModerationDecision::Approved, $notes));
    }

    public function reject(Artwork $artwork, User $reviewer, string $notes): void
    {
        $this->assertStatus($artwork, [ArtworkStatus::PendingReview]);

        DB::transaction(function () use ($artwork, $reviewer, $notes) {
            $artwork->forceFill(['status' => ArtworkStatus::Rejected])->save();
            $this->record($artwork, $reviewer, ModerationDecision::Rejected, $notes);
            $this->audit->log('artwork.rejected', $artwork, ['notes' => $notes], $reviewer);
        });

        $artwork->artist->user->notify(new ArtworkReviewed($artwork, ModerationDecision::Rejected, $notes));
    }

    public function suspend(Artwork $artwork, User $reviewer, string $reason): void
    {
        $this->assertStatus($artwork, [ArtworkStatus::Approved, ArtworkStatus::PendingReview, ArtworkStatus::Draft, ArtworkStatus::Rejected]);

        DB::transaction(function () use ($artwork, $reviewer, $reason) {
            $artwork->forceFill(['status' => ArtworkStatus::Suspended])->save();
            $this->record($artwork, $reviewer, ModerationDecision::Suspended, $reason);
            $this->audit->log('artwork.suspended', $artwork, ['reason' => $reason], $reviewer);
        });

        $artwork->artist->user->notify(new ArtworkReviewed($artwork, ModerationDecision::Suspended, $reason));
    }

    public function reinstate(Artwork $artwork, User $reviewer, ?string $notes = null): void
    {
        $this->assertStatus($artwork, [ArtworkStatus::Suspended]);

        DB::transaction(function () use ($artwork, $reviewer, $notes) {
            $artwork->forceFill(['status' => ArtworkStatus::Approved])->save();
            $this->record($artwork, $reviewer, ModerationDecision::Reinstated, $notes);
            $this->audit->log('artwork.reinstated', $artwork, ['notes' => $notes], $reviewer);
        });
    }

    /**
     * @param  list<ArtworkStatus>  $allowed
     */
    private function assertStatus(Artwork $artwork, array $allowed): void
    {
        if (! in_array($artwork->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('This design is :status and cannot be changed this way.', ['status' => $artwork->status->label()])]);
        }
    }

    private function record(Artwork $artwork, User $user, ModerationDecision $decision, ?string $notes): void
    {
        $artwork->moderationReviews()->create([
            'artwork_version_id' => $artwork->current_version_id,
            'user_id' => $user->id,
            'decision' => $decision,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}
