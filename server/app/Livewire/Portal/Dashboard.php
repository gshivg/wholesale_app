<?php

namespace App\Livewire\Portal;

use App\Models\Coupon;
use App\Models\Deal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Home')]
class Dashboard extends Component
{
    public function render()
    {
        $u = auth()->user();
        $orders = $u->orders();

        return view('livewire.portal.dashboard', [
            'shop' => $u->shop,
            'active' => $u->orders()->whereNotIn('status', ['delivered', 'cancelled', 'returned'])->latest()->limit(3)->get(),
            'recent' => $u->orders()->withCount('items')->latest()->limit(5)->get(),
            'monthSpend' => (float) $u->orders()->whereNotIn('status', ['cancelled', 'returned'])->where('created_at', '>=', now()->startOfMonth())->sum('total'),
            'openCount' => $u->orders()->whereNotIn('status', ['delivered', 'cancelled', 'returned'])->count(),
            'deliveredCount' => $u->orders()->where('status', 'delivered')->count(),
            'coupons' => Coupon::live()->where('is_public', true)->limit(3)->get(),
            'deals' => Deal::live()->limit(3)->get(),
            'openTickets' => $u->tickets()->whereIn('status', ['open', 'in_progress'])->count(),
        ]);
    }
}
