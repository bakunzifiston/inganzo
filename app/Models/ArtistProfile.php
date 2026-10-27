<?php

namespace App\Models;

use App\Enums\ArtistStatus;
use App\Enums\ArtworkStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * An artist account and its public shop (/artists/{slug}).
 */
#[Fillable([
    'user_id', 'display_name', 'slug', 'tagline', 'bio', 'location', 'avatar_path', 'banner_path',
    'website_url', 'instagram_handle', 'x_handle', 'agreement_accepted_at', 'agreement_version',
])]
class ArtistProfile extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ArtistStatus::class,
            'agreement_accepted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_featured' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Artwork, $this>
     */
    public function artworks(): HasMany
    {
        return $this->hasMany(Artwork::class);
    }

    /**
     * @return HasMany<FinishedProduct, $this>
     */
    public function finishedProducts(): HasMany
    {
        return $this->hasMany(FinishedProduct::class);
    }

    /**
     * @return HasMany<Artwork, $this>
     */
    public function publishedArtworks(): HasMany
    {
        return $this->hasMany(Artwork::class)->where('status', ArtworkStatus::Approved);
    }

    /**
     * @return HasMany<ArtCollection, $this>
     */
    public function collections(): HasMany
    {
        return $this->hasMany(ArtCollection::class)->orderBy('position');
    }

    /**
     * @return HasMany<DesignAsset, $this>
     */
    public function designAssets(): HasMany
    {
        return $this->hasMany(DesignAsset::class);
    }

    /**
     * @return HasMany<PayoutAccount, $this>
     */
    public function payoutAccounts(): HasMany
    {
        return $this->hasMany(PayoutAccount::class);
    }

    /**
     * @return HasOne<PayoutAccount, $this>
     */
    public function defaultPayoutAccount(): HasOne
    {
        return $this->hasOne(PayoutAccount::class)->where('is_default', true)->latestOfMany();
    }

    /**
     * @return HasMany<PayoutRequest, $this>
     */
    public function payoutRequests(): HasMany
    {
        return $this->hasMany(PayoutRequest::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id')->where('account_type', 'artist');
    }

    /**
     * @param  Builder<ArtistProfile>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', ArtistStatus::Approved);
    }

    public function isApproved(): bool
    {
        return $this->status === ArtistStatus::Approved;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function bannerUrl(): ?string
    {
        return $this->banner_path ? Storage::disk('public')->url($this->banner_path) : null;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->display_name, 0, 1));
    }
}
