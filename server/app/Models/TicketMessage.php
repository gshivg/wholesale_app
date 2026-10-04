<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketMessage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> */
    public function attachmentUrls(): array
    {
        return collect($this->attachments ?? [])->map(fn ($p) => Storage::disk('public')->url($p))->all();
    }
}
