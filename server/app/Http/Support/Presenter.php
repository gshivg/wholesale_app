<?php

namespace App\Http\Support;

use App\Models\Deal;
use App\Models\Order;
use App\Models\Product;
use App\Models\Ticket;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use App\Support\Money;
use App\Support\OrderStatus;
use Illuminate\Notifications\DatabaseNotification;

/** JSON shapes for the mobile app. Money is always rupees (float), never paise. */
class Presenter
{
    public static function user(User $u): array
    {
        $u->loadMissing('shop.hub');
        $s = $u->shop;

        return [
            'id' => $u->id,
            'name' => $u->name,
            'phone' => $u->phone,
            'email' => $u->email,
            'role' => $u->role->value,
            'locale' => $u->locale,
            'phone_verified' => (bool) $u->phone_verified_at,
            'can_order' => $u->canOrder(),
            'shop' => $s ? [
                'id' => $s->id,
                'shop_name' => $s->shop_name,
                'shop_type' => $s->shop_type,
                'gstin' => $s->gstin,
                'pan' => $s->pan,
                'address_line1' => $s->address_line1,
                'address_line2' => $s->address_line2,
                'landmark' => $s->landmark,
                'city' => $s->city,
                'district' => $s->district,
                'state' => $s->state,
                'pincode' => $s->pincode,
                'photo_url' => $s->photo_url,
                'status' => $s->status,
                'status_note' => $s->status_note,
                'credit_limit' => (float) $s->credit_limit,
                'credit_used' => (float) $s->credit_used,
                'credit_available' => $s->credit_available,
                'hub' => $s->hub ? ['code' => $s->hub->code, 'name' => $s->hub->name] : null,
            ] : null,
        ];
    }

    public static function product(Product $p, ?int $hubId = null, $deals = null): array
    {
        $stock = app(StockService::class);
        $deals ??= Deal::live()->get();
        $p->loadMissing('category', 'variants');
        $variants = $p->variants->where('is_active', true)->values();

        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'brand' => $p->brand,
            'description' => $p->description,
            'category' => ['id' => $p->category->id, 'name' => $p->category->name, 'icon' => $p->category->icon],
            'image_url' => $p->image_url,
            'accent' => $p->accent,
            'gst_rate' => (float) $p->gst_rate,
            'hsn_code' => $p->hsn_code,
            'is_featured' => $p->is_featured,
            'variants' => $variants->map(function ($v) use ($stock, $hubId) {
                $v->setRelation('product', null);
                $avail = $hubId ? $stock->available($hubId, $v->id) : null;

                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'label' => $v->label,
                    'packs_per_case' => $v->packs_per_case,
                    'price' => (float) $v->price,
                    'mrp' => (float) $v->mrp,
                    'pack_price' => $v->pack_price,
                    'min_qty' => $v->min_qty,
                    'max_qty' => $v->max_qty,
                    'available' => $avail,
                    'in_stock' => $avail === null ? true : $avail >= $v->min_qty,
                ];
            })->all(),
            'deals' => $deals->filter(fn (Deal $d) => $variants->contains(fn ($v) => self::dealApplies($d, $p, $v)))
                ->map(fn (Deal $d) => self::deal($d))->values()->all(),
        ];
    }

    private static function dealApplies(Deal $d, Product $p, $v): bool
    {
        if ($d->product_variant_id) {
            return $d->product_variant_id === $v->id;
        }
        if ($d->product_id) {
            return $d->product_id === $p->id;
        }
        if ($d->category_id) {
            return $d->category_id === $p->category_id;
        }

        return true;
    }

    public static function deal(Deal $d): array
    {
        return [
            'id' => $d->id,
            'title' => $d->title,
            'description' => $d->description,
            'type' => $d->type,
            'summary' => $d->summary(),
            'min_qty' => $d->min_qty,
            'ends_at' => $d->ends_at?->toIso8601String(),
            'product_id' => $d->product_id,
            'category_id' => $d->category_id,
        ];
    }

    /** Convert a PricingService quote (paise) to rupees for clients. */
    public static function quote(array $q): array
    {
        $r = fn (int $p) => Money::rupees($p);
        $products = Product::whereIn('id', array_column($q['lines'], 'product_id'))->get()->keyBy('id');

        return [
            'lines' => array_map(fn ($l) => [
                'variant_id' => $l['variant_id'],
                'product_id' => $l['product_id'],
                'name' => $l['name'],
                'label' => $l['label'],
                'image_url' => $products[$l['product_id']]->image_url ?? null,
                'accent' => $products[$l['product_id']]->accent ?? null,
                'quantity' => $l['qty'],
                'free_quantity' => $l['free_qty'],
                'unit_price' => $r($l['unit']),
                'mrp' => $r($l['mrp']),
                'gross' => $r($l['gross']),
                'deal_discount' => $r($l['deal']),
                'deal_title' => $l['deal_title'],
                'taxable' => $r($l['taxable']),
                'gst_rate' => $l['gst_rate'],
                'tax' => $r($l['tax']),
                'total' => $r($l['total']),
                'min_qty' => $l['min_qty'],
                'available' => $l['available'],
            ], $q['lines']),
            'totals' => [
                'gross' => $r($q['totals']['gross']),
                'deal_discount' => $r($q['totals']['deal_discount']),
                'subtotal' => $r($q['totals']['subtotal']),
                'slab_percent' => $q['totals']['slab_percent'],
                'slab_discount' => $r($q['totals']['slab_discount']),
                'coupon_discount' => $r($q['totals']['coupon_discount']),
                'taxable' => $r($q['totals']['taxable']),
                'cgst' => $r($q['totals']['cgst']),
                'sgst' => $r($q['totals']['sgst']),
                'igst' => $r($q['totals']['igst']),
                'tax' => $r($q['totals']['tax']),
                'round_off' => $r($q['totals']['round_off']),
                'total' => $r($q['totals']['total']),
                'total_cases' => $q['totals']['total_cases'],
                'free_cases' => $q['totals']['free_cases'],
                'savings' => $r($q['totals']['savings']),
            ],
            'coupon' => ['code' => $q['coupon']['code'], 'applied' => $q['coupon']['applied'], 'message' => $q['coupon']['message'], 'discount' => $r($q['coupon']['discount'])],
            'next_slab' => $q['next_slab'] ? ['min_subtotal' => $q['next_slab']['min_subtotal'], 'percent' => $q['next_slab']['percent'], 'remaining' => $r($q['next_slab']['remaining'])] : null,
            'minimums' => $q['minimums'],
            'errors' => $q['errors'],
            'can_checkout' => (bool) $q['can_checkout'],
        ];
    }

    public static function orderSummary(Order $o): array
    {
        return [
            'id' => $o->id,
            'number' => $o->number,
            'status' => $o->status,
            'status_label' => $o->statusLabel(),
            'payment_method' => $o->payment_method,
            'payment_status' => $o->payment_status,
            'total' => (float) $o->total,
            'total_cases' => $o->total_cases,
            'item_count' => $o->items_count ?? $o->items->count(),
            'placed_at' => $o->created_at->toIso8601String(),
        ];
    }

    public static function order(Order $o): array
    {
        $o->loadMissing('items', 'events.user', 'agent', 'hub');
        $invoice = app(InvoiceService::class);
        $stages = collect(config('shared.orders.stages'))->map(function ($s) use ($o) {
            $at = $s['key'] === 'placed' ? $o->created_at : $o->{$s['key'] . '_at'};

            return ['key' => $s['key'], 'label' => $s['label'], 'description' => $s['description'], 'reached' => (bool) $at, 'at' => $at?->toIso8601String()];
        })->all();

        return self::orderSummary($o) + [
            'stages' => $stages,
            'is_final' => OrderStatus::isFinal($o->status),
            'cancel_reason' => $o->cancel_reason,
            'can_cancel' => $o->isCustomerCancellable(),
            'can_pay' => $o->payment_method === 'online' && $o->payment_status !== 'paid' && in_array($o->status, ['placed', 'confirmed'], true),
            'delivery_otp' => in_array($o->status, ['dispatched', 'out_for_delivery'], true) ? $o->delivery_otp : null,
            'agent' => $o->agent && in_array($o->status, ['dispatched', 'out_for_delivery'], true) ? ['name' => $o->agent->name, 'phone' => $o->agent->phone] : null,
            'hub' => ['code' => $o->hub->code, 'name' => $o->hub->name],
            'notes' => $o->notes,
            'address' => $o->shipping_address,
            'invoice_available' => $invoice->available($o),
            'items' => $o->items->map(fn ($i) => [
                'id' => $i->id, 'variant_id' => $i->product_variant_id, 'name' => $i->name, 'label' => $i->label,
                'quantity' => $i->quantity, 'free_quantity' => $i->free_quantity,
                'unit_price' => (float) $i->unit_price, 'deal_title' => $i->deal_title,
                'taxable' => (float) $i->taxable, 'gst_rate' => (float) $i->gst_rate, 'tax' => (float) $i->tax, 'total' => (float) $i->total,
            ])->all(),
            'totals' => [
                'gross' => (float) $o->gross_amount, 'deal_discount' => (float) $o->deal_discount,
                'slab_discount' => (float) $o->slab_discount, 'coupon_discount' => (float) $o->coupon_discount,
                'coupon_code' => $o->coupon_code, 'taxable' => (float) $o->taxable_amount,
                'cgst' => (float) $o->cgst, 'sgst' => (float) $o->sgst, 'igst' => (float) $o->igst,
                'round_off' => (float) $o->round_off, 'total' => (float) $o->total,
            ],
            'events' => $o->events->map(fn ($e) => [
                'status' => $e->status, 'label' => OrderStatus::label($e->status), 'note' => $e->note, 'at' => $e->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    public static function ticket(Ticket $t, bool $withMessages = false): array
    {
        $t->loadMissing('order', 'agent');
        $out = [
            'id' => $t->id,
            'number' => $t->number,
            'subject' => $t->subject,
            'category' => $t->category,
            'category_label' => $t->categoryLabel(),
            'status' => $t->status,
            'priority' => $t->priority,
            'order' => $t->order ? ['id' => $t->order->id, 'number' => $t->order->number] : null,
            'agent_name' => $t->agent?->name,
            'rating' => $t->rating,
            'created_at' => $t->created_at->toIso8601String(),
            'last_reply_at' => $t->last_reply_at?->toIso8601String(),
        ];
        if ($withMessages) {
            $out['messages'] = $t->messages()->where('is_internal', false)->with('user')->get()->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'from_staff' => $m->user?->isStaff() ?? true,
                'author' => $m->user?->isStaff() ? ($m->user->name . ' (Support)') : 'You',
                'attachments' => $m->attachmentUrls(),
                'at' => $m->created_at->toIso8601String(),
            ])->all();
        }

        return $out;
    }

    public static function notification(DatabaseNotification $n): array
    {
        return [
            'id' => $n->id,
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'kind' => $n->data['kind'] ?? 'info',
            'order_id' => $n->data['order_id'] ?? null,
            'ticket_id' => $n->data['ticket_id'] ?? null,
            'read' => $n->read_at !== null,
            'at' => $n->created_at->toIso8601String(),
        ];
    }
}
