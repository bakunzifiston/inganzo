<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'recipient_name', 'phone', 'country_id', 'district', 'sector', 'street_address', 'landmark'])]
class OrderAddress extends Model
{
    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function summary(): string
    {
        return collect([$this->street_address, $this->sector, $this->district, $this->country?->name])->filter()->implode(', ');
    }
}
