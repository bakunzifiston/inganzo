<?php

namespace App\Models;

use App\Enums\ArtistStatus;
use App\Enums\ArtworkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Reusable source design. Visible publicly only when approved and its artist is approved.
 */
#[Fillable(['artist_profile_id', 'category_id', 'title', 'slug', 'description', 'is_mature', 'rights_confirmed_at'])]
#[Hidden(['search_vector'])]
class Artwork extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ArtworkStatus::class,
            'rights_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'is_mature' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ArtworkVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ArtworkVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return BelongsTo<ArtworkVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ArtworkVersion::class, 'current_version_id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * @return HasMany<ArtistProduct, $this>
     */
    public function artistProducts(): HasMany
    {
        return $this->hasMany(ArtistProduct::class);
    }

    /**
     * @return HasMany<ModerationReview, $this>
     */
    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ModerationReview::class)->latest('created_at');
    }

    /**
     * @return HasOne<DesignDocument, $this>
     */
    public function designDocument(): HasOne
    {
        return $this->hasOne(DesignDocument::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites');
    }

    /**
     * @return BelongsToMany<ArtCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ArtCollection::class, 'collection_items', 'artwork_id', 'collection_id');
    }

    /**
     * Approved artwork from an approved artist: the only artwork shown publicly.
     *
     * @param  Builder<Artwork>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('artworks.status', ArtworkStatus::Approved)
            ->whereHas('artist', fn (Builder $q) => $q->where('status', ArtistStatus::Approved));
    }

    public function isPublic(): bool
    {
        return $this->status === ArtworkStatus::Approved && $this->artist?->status === ArtistStatus::Approved;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [ArtworkStatus::Draft, ArtworkStatus::Rejected, ArtworkStatus::Approved], true);
    }

    public function latestRejection(): ?ModerationReview
    {
        return $this->moderationReviews->firstWhere('decision', 'rejected');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
