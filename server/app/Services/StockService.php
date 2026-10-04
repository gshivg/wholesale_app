<?php

namespace App\Services;

use App\Models\Hub;
use App\Models\HubStock;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Case-level stock per hub. `quantity` = on hand, `reserved` = promised to open orders.
 * Always call inside a DB transaction (rows are locked).
 */
class StockService
{
    public function row(int $hubId, int $variantId, bool $lock = true): HubStock
    {
        $q = HubStock::where(['hub_id' => $hubId, 'product_variant_id' => $variantId]);
        if ($lock) {
            $q->lockForUpdate();
        }

        return $q->first() ?? HubStock::create([
            'hub_id' => $hubId,
            'product_variant_id' => $variantId,
            'quantity' => 0,
            'reserved' => 0,
            'low_stock_at' => config('wholesale.low_stock_default'),
        ]);
    }

    public function available(int $hubId, int $variantId): int
    {
        $s = HubStock::where(['hub_id' => $hubId, 'product_variant_id' => $variantId])->first();

        return $s ? $s->available : 0;
    }

    public function reserve(int $hubId, int $variantId, int $qty, ?Model $ref = null): void
    {
        $s = $this->row($hubId, $variantId);
        if ($s->available < $qty) {
            throw new RuntimeException('Insufficient stock');
        }
        $s->increment('reserved', $qty);
        $this->log($hubId, $variantId, 'reserve', $qty, $ref);
    }

    public function release(int $hubId, int $variantId, int $qty, ?Model $ref = null): void
    {
        $s = $this->row($hubId, $variantId);
        $s->update(['reserved' => max(0, $s->reserved - $qty)]);
        $this->log($hubId, $variantId, 'release', $qty, $ref);
    }

    /** Goods physically leave the hub. */
    public function ship(int $hubId, int $variantId, int $qty, ?Model $ref = null): void
    {
        $s = $this->row($hubId, $variantId);
        $s->update([
            'quantity' => $s->quantity - $qty,
            'reserved' => max(0, $s->reserved - $qty),
        ]);
        $this->log($hubId, $variantId, 'ship', $qty, $ref);
    }

    public function restock(int $hubId, int $variantId, int $qty, string $type = 'return', ?Model $ref = null, ?User $by = null, ?string $note = null): void
    {
        $s = $this->row($hubId, $variantId);
        $s->increment('quantity', $qty);
        $this->log($hubId, $variantId, $type, $qty, $ref, $by, $note);
    }

    public function adjust(int $hubId, int $variantId, int $newQuantity, ?User $by = null, ?string $note = null): void
    {
        DB::transaction(function () use ($hubId, $variantId, $newQuantity, $by, $note) {
            $s = $this->row($hubId, $variantId);
            $delta = $newQuantity - $s->quantity;
            $s->update(['quantity' => $newQuantity]);
            $this->log($hubId, $variantId, 'adjust', $delta, null, $by, $note);
        });
    }

    /** Main warehouse → distribution centre. */
    public function transfer(Hub $from, Hub $to, int $variantId, int $qty, ?User $by = null): void
    {
        DB::transaction(function () use ($from, $to, $variantId, $qty, $by) {
            $src = $this->row($from->id, $variantId);
            if ($src->available < $qty) {
                throw new RuntimeException("Only {$src->available} cases available at {$from->name}");
            }
            $src->decrement('quantity', $qty);
            $this->log($from->id, $variantId, 'transfer_out', $qty, $to, $by, "To {$to->name}");
            $this->row($to->id, $variantId)->increment('quantity', $qty);
            $this->log($to->id, $variantId, 'transfer_in', $qty, $from, $by, "From {$from->name}");
        });
    }

    private function log(int $hubId, int $variantId, string $type, int $qty, ?Model $ref = null, ?User $by = null, ?string $note = null): void
    {
        StockMovement::create([
            'hub_id' => $hubId,
            'product_variant_id' => $variantId,
            'type' => $type,
            'quantity' => $qty,
            'reference_type' => $ref?->getMorphClass(),
            'reference_id' => $ref?->getKey(),
            'user_id' => ($by ?? auth()->user())?->id,
            'note' => $note,
        ]);
    }
}
