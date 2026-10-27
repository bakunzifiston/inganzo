<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A manufacturable blank, e.g. "Classic T-shirt". Artists apply artwork to blanks.
 */
#[Fillable([
    'product_type_id', 'slug', 'name', 'description', 'base_cost_amount', 'currency', 'production_days',
    'weight_grams', 'is_shippable', 'is_active', 'mockup_template', 'position',
])]
class Product extends Model
{
    use HasFactory, HasTranslations;

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'base_cost_amount' => 'integer',
            'is_shippable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ProductType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(ProductType::class, 'product_type_id');
    }

    /**
     * @return HasMany<ProductColor, $this>
     */
    public function colors(): HasMany
    {
        return $this->hasMany(ProductColor::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductSize, $this>
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<PrintArea, $this>
     */
    public function printAreas(): HasMany
    {
        return $this->hasMany(PrintArea::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductMockup, $this>
     */
    public function mockups(): HasMany
    {
        return $this->hasMany(ProductMockup::class)->orderBy('position');
    }

    /**
     * @return HasMany<PrintingPartnerService, $this>
     */
    public function partnerServices(): HasMany
    {
        return $this->hasMany(PrintingPartnerService::class);
    }

    /**
     * @param  Builder<Product>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function primaryPrintArea(): ?PrintArea
    {
        return $this->printAreas->first();
    }

    /**
     * Mockup for a colour and print area, preferring a colour-specific photo over the shared template.
     */
    public function mockupFor(PrintArea $area, ?ProductColor $color = null): ?ProductMockup
    {
        $candidates = $this->mockups->where('print_area_id', $area->id);

        return $candidates->first(fn (ProductMockup $mockup) => $color && $mockup->product_color_id === $color->id)
            ?? $candidates->firstWhere('product_color_id', null);
    }
}
