<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A curated (platform) or artist-owned group of artworks.
 */
#[Table('collections')]
#[Fillable(['artist_profile_id', 'slug', 'title', 'description', 'cover_path', 'is_featured', 'is_active', 'position'])]
class ArtCollection extends Model
{
    use HasTranslations;

    protected function casts(): array
    {
        return ['title' => 'array', 'description' => 'array', 'is_featured' => 'boolean', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<ArtistProfile, $this>
     */
    public function artist(): BelongsTo
    {
        return $this->belongsTo(ArtistProfile::class, 'artist_profile_id');
    }

    /**
     * @return BelongsToMany<Artwork, $this>
     */
    public function artworks(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'collection_items', 'collection_id', 'artwork_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * @param  Builder<ArtCollection>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
