<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'legal_name', 'email', 'phone', 'country_id', 'city', 'address', 'daily_capacity', 'priority', 'is_active', 'notes'])]
class PrintingPartner extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'daily_capacity' => 'integer', 'priority' => 'integer'];
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<PartnerMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(PartnerMember::class);
    }

    /**
     * @return HasMany<PrintingPartnerService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(PrintingPartnerService::class);
    }

    /**
     * @return HasMany<PartnerServiceArea, $this>
     */
    public function serviceAreas(): HasMany
    {
        return $this->hasMany(PartnerServiceArea::class);
    }

    /**
     * @return HasMany<ProductionJob, $this>
     */
    public function productionJobs(): HasMany
    {
        return $this->hasMany(ProductionJob::class);
    }
}
