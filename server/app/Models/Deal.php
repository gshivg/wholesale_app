<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeLive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function appliesTo(ProductVariant $v): bool
    {
        if ($this->product_variant_id) {
            return $this->product_variant_id === $v->id;
        }
        if ($this->product_id) {
            return $this->product_id === $v->product_id;
        }
        if ($this->category_id) {
            return $this->category_id === $v->product->category_id;
        }

        return true;
    }

    public function summary(): string
    {
        return match ($this->type) {
            'percent' => rtrim(rtrim((string) $this->value, '0'), '.') . '% off',
            'flat' => '₹' . number_format((float) $this->value, 0) . ' off per case',
            'bxgy' => "Buy {$this->buy_qty}, get {$this->free_qty} free",
            default => $this->title,
        };
    }
}
