<?php

namespace App\Models;

use App\Enums\ArtistStatus;
use App\Enums\ArtworkStatus;
use App\Enums\MarkupType;
use App\Enums\PrintCheckStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An artwork offered on a blank product, with placement, enabled colours and artist markup.
 */
#[Fillable(['artwork_id', 'product_id', 'is_enabled', 'markup_type', 'markup_bps', 'markup_amount', 'default_color_id'])]
class ArtistProduct extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'markup_type' => MarkupType::class,
            'markup_bps' => 'integer',
            'markup_amount' => 'integer',
            'price_from_amount' => 'integer',
            'print_check_status' => PrintCheckStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Artwork, $this>
     */
    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ArtistProductPlacement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(ArtistProductPlacement::class);
    }

    /**
     * @return BelongsToMany<ProductColor, $this>
     */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(ProductColor::class, 'artist_product_colors')->orderBy('position');
    }

    /**
     * @return BelongsTo<ProductColor, $this>
     */
    public function defaultColor(): BelongsTo
    {
        return $this->belongsTo(ProductColor::class, 'default_color_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Products a customer can buy right now.
     *
     * @param  Builder<ArtistProduct>  $query
     */
    public function scopeListed(Builder $query): void
    {
        $query->where('artist_products.is_enabled', true)
            ->whereIn('artist_products.print_check_status', [PrintCheckStatus::Pass->value, PrintCheckStatus::Warn->value])
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true))
            ->whereHas('artwork', fn (Builder $q) => $q->where('status', ArtworkStatus::Approved)
                ->whereHas('artist', fn (Builder $a) => $a->where('status', ArtistStatus::Approved)));
    }

    public function isListed(): bool
    {
        return $this->is_enabled
            && in_array($this->print_check_status, [PrintCheckStatus::Pass, PrintCheckStatus::Warn], true)
            && $this->product->is_active
            && $this->artwork->isPublic();
    }

    public function displayColor(): ?ProductColor
    {
        return $this->defaultColor ?? $this->colors->first();
    }

    public function placementFor(PrintArea $area): ?ArtistProductPlacement
    {
        return $this->placements->firstWhere('print_area_id', $area->id);
    }

    public function title(): string
    {
        return $this->artwork->title.' — '.$this->product->translate('name');
    }
}
