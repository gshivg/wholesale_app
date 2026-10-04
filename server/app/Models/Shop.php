<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Shop extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'credit_used' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hub(): BelongsTo
    {
        return $this->belongsTo(Hub::class);
    }

    public function getCreditAvailableAttribute(): float
    {
        return max(0, (float) $this->credit_limit - (float) $this->credit_used);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function addressSnapshot(): array
    {
        return [
            'name' => $this->shop_name,
            'contact' => $this->user->name,
            'phone' => $this->user->phone,
            'line1' => $this->address_line1,
            'line2' => $this->address_line2,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'district' => $this->district,
            'state' => $this->state,
            'state_code' => $this->state_code,
            'pincode' => $this->pincode,
            'gstin' => $this->gstin,
        ];
    }
}
