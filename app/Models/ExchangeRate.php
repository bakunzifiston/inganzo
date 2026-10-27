<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['base_currency', 'quote_currency', 'rate', 'effective_at', 'source'])]
class ExchangeRate extends Model
{
    protected function casts(): array
    {
        return ['effective_at' => 'datetime', 'rate' => 'decimal:10'];
    }
}
