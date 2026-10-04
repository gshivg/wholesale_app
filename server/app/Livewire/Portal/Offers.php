<?php

namespace App\Livewire\Portal;

use App\Models\Coupon;
use App\Models\Deal;
use App\Models\DiscountSlab;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Offers')]
class Offers extends Component
{
    public string $code = '';

    /** Save a (possibly private, shared-by-code) coupon to the cart so it applies at checkout. */
    public function redeem(?string $code = null): void
    {
        $code = trim($code ?? $this->code);
        $user = auth()->user();
        abort_unless($user->canOrder(), 403);
        [$c, $msg] = app(\App\Services\PricingService::class)->validateCoupon($user, $code, PHP_INT_MAX);
        if (! $c) {
            $this->addError('code', $msg);

            return;
        }
        \App\Models\Cart::forUser($user)->update(['coupon_code' => $c->code]);
        $this->code = '';
        $this->resetErrorBag();
        $this->dispatch('toast', message: "{$c->code} saved — it will apply when your cart qualifies.", kind: 'success');
    }

    public function render()
    {
        return view('livewire.portal.offers', [
            'slabs' => DiscountSlab::where('is_active', true)->orderBy('min_subtotal')->get(),
            'deals' => Deal::live()->with('product', 'category')->get(),
            'coupons' => Coupon::live()->where('is_public', true)->get(),
            'saved' => auth()->user()->cart?->coupon_code,
        ]);
    }
}
