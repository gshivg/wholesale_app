<?php

namespace App\Livewire\Staff;

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Support\OrderStatus;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.staff')]
#[Title('Order')]
class OrderShow extends Component
{
    public Order $order;
    public string $note = '';
    public ?int $agentId = null;
    public bool $confirmCancel = false;

    public function mount(Order $order): void
    {
        abort_unless(auth()->user()->hasRole(Role::SuperAdmin, Role::OrderManager), 403);
        $this->order = $order;
    }

    public function move(string $to): void
    {
        try {
            $this->order = app(OrderService::class)->transition($this->order, $to, auth()->user(), ['note' => $this->note ?: null]);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), kind: 'error');

            return;
        }
        $this->note = '';
        $this->confirmCancel = false;
        $this->dispatch('toast', message: 'Order updated', kind: 'success');
    }

    public function reassign(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $this->order->update(['delivery_agent_id' => $this->agentId]);
        $this->dispatch('toast', message: 'Delivery agent reassigned', kind: 'success');
    }

    public function markPaid(): void
    {
        $this->order->update(['payment_status' => 'paid']);
        app(OrderService::class)->log($this->order, $this->order->status, auth()->user(), 'Payment marked as received');
        if ($this->order->payment_method === 'credit') {
            $shop = $this->order->shop;
            $shop->update(['credit_used' => max(0, (float) $shop->credit_used - (float) $this->order->total)]);
        }
    }

    public function render()
    {
        $this->order->refresh()->load('items', 'events.user', 'shop.user', 'hub', 'agent', 'tickets', 'payments');
        $u = auth()->user();
        $next = OrderStatus::next($this->order->status);
        $stageRole = $next ? collect(config('shared.orders.stages'))->firstWhere('key', $next)['actor'] : null;
        // Order managers can confirm; super admin can push any stage (e.g. to unblock a station).
        $canAdvance = $next && ($u->isSuperAdmin() || ($next === 'confirmed' && $u->role === Role::OrderManager));

        return view('livewire.staff.order-show', [
            'o' => $this->order, 'next' => $next, 'stageRole' => $stageRole, 'canAdvance' => $canAdvance,
            'canCancel' => ! in_array($this->order->status, ['dispatched', 'out_for_delivery', 'delivered', 'cancelled', 'returned']),
            'canReturn' => $this->order->status === 'delivered',
            'agents' => User::where('role', Role::Delivery->value)->where('hub_id', $this->order->hub_id)->get(),
        ]);
    }
}
