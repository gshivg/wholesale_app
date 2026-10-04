<?php

namespace App\Livewire\Portal;

use App\Models\Cart as CartModel;
use App\Models\Coupon;
use App\Models\ProductVariant;
use App\Services\OrderService;
use App\Services\PricingService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Cart')]
class Cart extends Component
{
    public string $coupon = '';
    public string $payment = 'cod';
    public string $notes = '';

    public function mount()
    {
        if (! auth()->user()->canOrder()) {
            session()->flash('status', 'Ordering unlocks once your account is approved.');

            return redirect()->route('portal');
        }
    }

    private function cart(): CartModel
    {
        return CartModel::forUser(auth()->user());
    }

    public function setQty(int $variantId, $qty): void
    {
        $qty = (int) $qty;
        $v = ProductVariant::findOrFail($variantId);
        if ($qty <= 0) {
            $this->cart()->items()->where('product_variant_id', $variantId)->delete();
        } else {
            $this->cart()->items()->updateOrCreate(['product_variant_id' => $variantId], ['quantity' => min($qty, $v->max_qty)]);
        }
        $this->dispatch('cart-updated');
    }

    public function step(int $variantId, int $delta): void
    {
        $cur = (int) $this->cart()->items()->where('product_variant_id', $variantId)->value('quantity');
        $this->setQty($variantId, $cur + $delta);
    }

    public function applyCoupon(?string $code = null): void
    {
        $code = trim($code ?? $this->coupon);
        if ($code === '') {
            return;
        }
        $cart = $this->cart();
        $quote = app(PricingService::class)->quote(auth()->user(), $cart->items()->pluck('quantity', 'product_variant_id')->all(), $code);
        if (! $quote['coupon']['applied']) {
            $this->addError('coupon', $quote['coupon']['message'] ?? 'Invalid coupon');

            return;
        }
        $cart->update(['coupon_code' => $quote['coupon']['code']]);
        $this->coupon = '';
        $this->resetErrorBag('coupon');
        $this->dispatch('toast', message: "Coupon {$quote['coupon']['code']} applied", kind: 'success');
    }

    public function removeCoupon(): void
    {
        $this->cart()->update(['coupon_code' => null]);
    }

    public function place()
    {
        try {
            $order = app(OrderService::class)->place(auth()->user(), $this->payment, $this->notes ?: null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $msgs) {
                $this->dispatch('toast', message: $msgs[0], kind: 'error');
            }

            return;
        }
        $this->dispatch('cart-updated');
        session()->flash('status', "Order {$order->number} placed!");

        return redirect()->route('portal.orders.show', ['order' => $order->id, 'pay' => $order->payment_method === 'online' ? 1 : 0]);
    }

    public function render()
    {
        $user = auth()->user();
        $cart = $this->cart()->fresh();
        $items = $cart->items()->pluck('quantity', 'product_variant_id')->all();
        $quote = app(PricingService::class)->quote($user, $items, $cart->coupon_code);
        if ($cart->coupon_code && ! $quote['coupon']['applied']) {
            $cart->update(['coupon_code' => null]);
        }

        return view('livewire.portal.cart', [
            'quote' => $quote,
            'shop' => $user->shop,
            'available' => Coupon::live()->where('is_public', true)->get(),
        ]);
    }
}
