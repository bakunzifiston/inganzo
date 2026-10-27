<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['printing_partner_id', 'product_id', 'unit_cost_amount', 'production_days', 'is_active'])]
class PrintingPartnerService extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'unit_cost_amount' => 'integer', 'production_days' => 'integer'];
    }

    /**
     * @return BelongsTo<PrintingPartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PrintingPartner::class, 'printing_partner_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
