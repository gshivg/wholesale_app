<?php

namespace App\Livewire;

use Livewire\Component;

class Notifications extends Component
{
    public function markAll(): void
    {
        auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('notifications-updated');
    }

    public function open(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $this->dispatch('notifications-updated');
        $u = auth()->user();
        $d = $n->data;
        if (! empty($d['order_id'])) {
            return redirect($u->isStaff() ? route('staff.orders.show', $d['order_id']) : route('portal.orders.show', $d['order_id']));
        }
        if (! empty($d['ticket_id'])) {
            return redirect($u->isStaff() ? route('staff.support.show', $d['ticket_id']) : route('portal.tickets.show', $d['ticket_id']));
        }
        if (! empty($d['shop_id']) && $u->isStaff()) {
            return redirect()->route('staff.shops.show', $d['shop_id']);
        }
    }

    public function render()
    {
        return view('livewire.notifications', ['items' => auth()->user()->notifications()->latest()->limit(60)->get()])
            ->layout(auth()->user()->isStaff() ? 'layouts.staff' : 'layouts.portal', ['title' => 'Notifications']);
    }
}
