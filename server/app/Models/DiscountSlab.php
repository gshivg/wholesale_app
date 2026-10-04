<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountSlab extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['min_subtotal' => 'decimal:2', 'percent' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
