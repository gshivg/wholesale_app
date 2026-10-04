<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use App\Notifications\AppNotice;
use App\Support\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(private PricingService $pricing, private StockService $stock) {}

    /** Re-quotes the user's cart and creates the order atomically. */
    public function place(User $user, string $paymentMethod, ?string $notes = null): Order
    {
        abort_unless($user->canOrder(), 403, 'Your account is not approved for ordering yet.');
        if (! in_array($paymentMethod, config('shared.orders.paymentMethods'), true)) {
            throw ValidationException::withMessages(['payment_method' => 'Invalid payment method.']);
        }

        return DB::transaction(function () use ($user, $paymentMethod, $notes) {
            $cart = Cart::forUser($user);
            $items = $cart->items()->pluck('quantity', 'product_variant_id')->all();
            if (! $items) {
                throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
            }
            $shop = $user->shop()->lockForUpdate()->first();

            // Lock stock rows for this hub so concurrent orders can't oversell.
            foreach (array_keys($items) as $variantId) {
                $this->stock->row($shop->hub_id, $variantId);
            }

            $quote = $this->pricing->quote($user, $items, $cart->coupon_code);
            if ($quote['errors']) {
                throw ValidationException::withMessages(['cart' => array_column($quote['errors'], 'message')]);
            }
            $t = $quote['totals'];
            $total = $t['total'] / 100;

            if ($paymentMethod === 'credit' && $shop->credit_available < $total) {
                throw ValidationException::withMessages(['payment_method' => 'Not enough credit. Available: ' . \App\Support\Money::fmt($shop->credit_available, true)]);
            }

            $order = Order::create([
                'number' => $this->nextNumber(),
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'hub_id' => $shop->hub_id,
                'status' => 'placed',
                'payment_method' => $paymentMethod,
                'payment_status' => 'pending',
                'gross_amount' => $t['gross'] / 100,
                'deal_discount' => $t['deal_discount'] / 100,
                'slab_discount' => $t['slab_discount'] / 100,
                'coupon_discount' => $t['coupon_discount'] / 100,
                'coupon_id' => $quote['coupon']['applied'] ? $quote['coupon']['id'] : null,
                'coupon_code' => $quote['coupon']['applied'] ? $quote['coupon']['code'] : null,
                'taxable_amount' => $t['taxable'] / 100,
                'cgst' => $t['cgst'] / 100,
                'sgst' => $t['sgst'] / 100,
                'igst' => $t['igst'] / 100,
                'round_off' => $t['round_off'] / 100,
                'total' => $total,
                'total_cases' => $t['total_cases'],
                'shipping_address' => $shop->addressSnapshot(),
                'notes' => $notes,
                'delivery_otp' => (string) random_int(1000, 9999),
            ]);

            foreach ($quote['lines'] as $l) {
                $order->items()->create([
                    'product_variant_id' => $l['variant_id'],
                    'name' => $l['name'],
                    'label' => $l['label'],
                    'sku' => $l['sku'],
                    'hsn_code' => $l['hsn_code'],
                    'quantity' => $l['qty'],
                    'free_quantity' => $l['free_qty'],
                    'packs_per_case' => $l['packs_per_case'],
                    'unit_price' => $l['unit'] / 100,
                    'mrp' => $l['mrp'] / 100,
                    'gross' => $l['gross'] / 100,
                    'deal_discount' => $l['deal'] / 100,
                    'slab_discount' => $l['slab'] / 100,
                    'coupon_discount' => $l['coupon'] / 100,
                    'taxable' => $l['taxable'] / 100,
                    'gst_rate' => $l['gst_rate'],
                    'tax' => $l['tax'] / 100,
                    'total' => $l['total'] / 100,
                    'deal_title' => $l['deal_title'],
                ]);
                $this->stock->reserve($shop->hub_id, $l['variant_id'], $l['qty'] + $l['free_qty'], $order);
            }

            if ($order->coupon_id) {
                CouponRedemption::create(['coupon_id' => $order->coupon_id, 'user_id' => $user->id, 'order_id' => $order->id, 'discount' => $order->coupon_discount]);
                $order->coupon->increment('times_used');
            }
            if ($paymentMethod === 'credit') {
                $shop->increment('credit_used', $total);
            }

            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);

            $this->log($order, 'placed', $user, 'Order placed');
            $user->notify(new AppNotice('Order placed', "Order {$order->number} for " . \App\Support\Money::fmt($total, true) . ' was placed.', 'order', ['order_id' => $order->id]));
            $this->notifyRole($order, [Role::OrderManager], 'New order', "{$order->number} from {$shop->shop_name}");

            return $order;
        });
    }

    /**
     * Move an order to $to, enforcing who may do what and that stages go in order.
     *
     * @param  array{note?:string,otp?:string,agent_id?:int}  $extra
     */
    public function transition(Order $order, string $to, User $actor, array $extra = []): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $extra) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            $this->authorize($order, $to, $actor);
            $from = $order->status;

            if ($to === OrderStatus::CANCELLED) {
                return $this->cancel($order, $actor, $extra['note'] ?? null);
            }
            if ($to === OrderStatus::RETURNED) {
                return $this->markReturned($order, $actor, $extra['note'] ?? null);
            }
            if (OrderStatus::next($from) !== $to) {
                throw ValidationException::withMessages(['status' => "Cannot move an order from {$from} to {$to}."]);
            }

            $updates = ['status' => $to, "{$to}_at" => now()];
            $note = $extra['note'] ?? null;

            switch ($to) {
                case 'confirmed':
                    if ($order->payment_method === 'online' && $order->payment_status !== 'paid') {
                        throw ValidationException::withMessages(['status' => 'Online payment is still pending for this order.']);
                    }
                    break;
                case 'dispatched':
                    foreach ($order->items as $i) {
                        $this->stock->ship($order->hub_id, $i->product_variant_id, $i->quantity + $i->free_quantity, $order);
                    }
                    $agentId = $extra['agent_id'] ?? $this->leastLoadedAgent($order->hub_id);
                    if ($agentId) {
                        $updates['delivery_agent_id'] = $agentId;
                        User::find($agentId)?->notify(new AppNotice('New delivery', "{$order->number} → {$order->shop->shop_name}", 'order', ['order_id' => $order->id]));
                    }
                    break;
                case 'delivered':
                    if (! $actor->isSuperAdmin() && ($extra['otp'] ?? null) !== $order->delivery_otp) {
                        throw ValidationException::withMessages(['otp' => 'Incorrect delivery OTP. Ask the shopkeeper for the 4-digit code shown in their app.']);
                    }
                    if ($order->payment_method === 'cod') {
                        $updates['payment_status'] = 'paid';
                        $note = trim(($note ?? '') . ' Cash collected.');
                    }
                    break;
            }

            $order->update($updates);
            $this->log($order, $to, $actor, $note);

            $tpl = collect(config('shared.orders.stages'))->firstWhere('key', $to);
            $order->user->notify(new AppNotice($tpl['label'] . ' · ' . $order->number, $tpl['description'], 'order', ['order_id' => $order->id]));
            if ($to === 'packed') {
                $this->notifyRole($order, [Role::Warehouse], 'Parcel packed', "{$order->number} is ready for dispatch", true);
            }
            if ($to === 'confirmed') {
                $this->notifyRole($order, [Role::Packaging], 'Order to pack', "{$order->number} is confirmed", true);
            }

            return $order->fresh();
        });
    }

    public function cancel(Order $order, User $actor, ?string $reason = null): Order
    {
        $wasShipped = in_array($order->status, ['dispatched', 'out_for_delivery', 'delivered'], true);
        abort_if($wasShipped, 422, 'This order has already left the warehouse.');

        foreach ($order->items as $i) {
            $this->stock->release($order->hub_id, $i->product_variant_id, $i->quantity + $i->free_quantity, $order);
        }
        $this->releaseCredit($order);
        if ($order->coupon_id) {
            $order->coupon?->decrement('times_used');
            CouponRedemption::where('order_id', $order->id)->delete();
        }
        $order->update([
            'status' => OrderStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
            'payment_status' => $order->payment_status === 'paid' ? 'refunded' : $order->payment_status,
        ]);
        $this->log($order, OrderStatus::CANCELLED, $actor, $reason ?: 'Order cancelled');
        if ($actor->id !== $order->user_id) {
            $order->user->notify(new AppNotice('Order cancelled', "{$order->number} was cancelled." . ($reason ? " Reason: {$reason}" : ''), 'order', ['order_id' => $order->id]));
        }

        return $order->fresh();
    }

    private function markReturned(Order $order, User $actor, ?string $reason): Order
    {
        foreach ($order->items as $i) {
            $this->stock->restock($order->hub_id, $i->product_variant_id, $i->quantity + $i->free_quantity, 'return', $order, $actor);
        }
        $this->releaseCredit($order);
        $order->update([
            'status' => OrderStatus::RETURNED,
            'payment_status' => $order->payment_status === 'paid' ? 'refunded' : $order->payment_status,
        ]);
        $this->log($order, OrderStatus::RETURNED, $actor, $reason ?: 'Order returned');
        $order->user->notify(new AppNotice('Order returned', "{$order->number} was marked as returned.", 'order', ['order_id' => $order->id]));

        return $order->fresh();
    }

    private function releaseCredit(Order $order): void
    {
        if ($order->payment_method === 'credit' && $order->payment_status !== 'paid') {
            $order->shop->update(['credit_used' => max(0, (float) $order->shop->credit_used - (float) $order->total)]);
        }
    }

    private function authorize(Order $order, string $to, User $actor): void
    {
        $role = $actor->role;
        $sameHub = $actor->hub_id === null || $actor->hub_id === $order->hub_id;
        $ok = match ($to) {
            'confirmed' => in_array($role, [Role::OrderManager, Role::SuperAdmin], true),
            'packing', 'packed' => in_array($role, [Role::Packaging, Role::SuperAdmin], true) && $sameHub,
            'dispatched' => in_array($role, [Role::Warehouse, Role::SuperAdmin], true) && $sameHub,
            'out_for_delivery', 'delivered' => $role === Role::SuperAdmin
                || ($role === Role::Delivery && $order->delivery_agent_id === $actor->id),
            OrderStatus::CANCELLED => $role === Role::Customer
                ? ($order->user_id === $actor->id && $order->isCustomerCancellable())
                : in_array($role, [Role::OrderManager, Role::SuperAdmin], true),
            OrderStatus::RETURNED => in_array($role, [Role::OrderManager, Role::SuperAdmin], true) && $order->status === 'delivered',
            default => false,
        };
        abort_unless($ok, 403, 'You are not allowed to make this change.');
    }

    private function leastLoadedAgent(int $hubId): ?int
    {
        return User::where('role', Role::Delivery->value)->where('hub_id', $hubId)->where('is_active', true)
            ->withCount(['deliveries as active_count' => fn ($q) => $q->whereIn('status', ['dispatched', 'out_for_delivery'])])
            ->orderBy('active_count')->orderBy('id')->value('id');
    }

    public function log(Order $order, string $status, ?User $by = null, ?string $note = null): OrderEvent
    {
        AuditLog::record("order.{$status}", $order, ['number' => $order->number], $by);

        return OrderEvent::create(['order_id' => $order->id, 'status' => $status, 'user_id' => $by?->id, 'note' => $note]);
    }

    private function notifyRole(Order $order, array $roles, string $title, string $body, bool $hubOnly = false): void
    {
        $q = User::whereIn('role', array_map(fn ($r) => $r->value, $roles))->where('is_active', true);
        if ($hubOnly) {
            $q->where('hub_id', $order->hub_id);
        }
        foreach ($q->get() as $u) {
            $u->notify(new AppNotice($title, $body, 'order', ['order_id' => $order->id]));
        }
    }

    private function nextNumber(): string
    {
        do {
            $n = 'RS' . now()->format('ymd') . '-' . random_int(1000, 9999);
        } while (Order::where('number', $n)->exists());

        return $n;
    }
}
