<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'order_id', 'printing_partner_id', 'carrier', 'tracking_number', 'tracking_url', 'status', 'created_by', 'dispatched_at', 'delivered_at'])]
class Shipment extends Model
{
    protected function casts(): array
    {
        return ['status' => ShipmentStatus::class, 'dispatched_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<PrintingPartner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PrintingPartner::class, 'printing_partner_id');
    }

    /**
     * @return HasMany<ShipmentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /**
     * @return HasMany<ShipmentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
