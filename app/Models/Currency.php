<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'symbol', 'minor_unit', 'symbol_first', 'is_active'])]
class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'symbol_first' => 'boolean', 'minor_unit' => 'integer'];
    }
}
