<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HubStock extends Model
{
    protected $guarded = [];

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function getAvailableAttribute(): int
    {
        return max(0, $this->quantity - $this->reserved);
    }

    public function isLow(): bool
    {
        return $this->available <= $this->low_stock_at;
    }
}
