<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order' => 'decimal:2',
            'is_public' => 'boolean',
            'first_order_only' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function scopeLive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function summary(): string
    {
        $v = $this->type === 'percent'
            ? rtrim(rtrim((string) $this->value, '0'), '.') . '% off'
            : '₹' . number_format((float) $this->value, 0) . ' off';
        if ($this->type === 'percent' && $this->max_discount) {
            $v .= ' (up to ₹' . number_format((float) $this->max_discount, 0) . ')';
        }

        return $v;
    }
}
