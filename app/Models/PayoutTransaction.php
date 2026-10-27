<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['payout_request_id', 'provider', 'reference', 'amount', 'currency', 'status', 'notes', 'executed_by', 'executed_at'])]
class PayoutTransaction extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer', 'executed_at' => 'datetime'];
    }
}
