<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'mrp' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(HubStock::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** Price of a single pack (case price / packs per case). */
    public function getPackPriceAttribute(): float
    {
        return round((float) $this->price / max(1, $this->packs_per_case), 2);
    }
}
