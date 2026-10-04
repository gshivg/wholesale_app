<?php

namespace App\Livewire\Portal;

use App\Models\Cart;
use Livewire\Attributes\On;
use Livewire\Component;

class Badges extends Component
{
    public bool $cart = true;

    #[On('cart-updated')]
    #[On('notifications-updated')]
    public function refresh(): void {}

    public function render()
    {
        $u = auth()->user();

        return view('livewire.portal.badges', [
            'cartCount' => $this->cart && $u->canOrder() ? (int) (Cart::where('user_id', $u->id)->first()?->items()->count() ?? 0) : 0,
            'unread' => $u->unreadNotifications()->count(),
        ]);
    }
}
