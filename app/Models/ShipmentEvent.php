<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['shipment_id', 'user_id', 'status', 'location', 'notes', 'occurred_at', 'created_at'])]
#[WithoutTimestamps]
class ShipmentEvent extends Model
{
    protected function casts(): array
    {
        return ['status' => ShipmentStatus::class, 'occurred_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
