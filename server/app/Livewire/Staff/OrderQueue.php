<?php

namespace App\Livewire\Staff;

use App\Enums\Role;
use App\Models\Hub;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One component, three station dashboards: packaging, warehouse and delivery.
 * Hub-scoped staff only ever see their own hub; super admins can switch hubs.
 */
#[Layout('layouts.staff')]
class OrderQueue extends Component
{
    public string $mode = 'packaging';
    #[Url]
    public string $tab = '';
    #[Url]
    public ?int $hub = null;
    public array $agent = [];   // order_id => agent user id (warehouse dispatch)
    public array $otp = [];     // order_id => typed OTP (delivery)
    public array $note = [];

    private const TABS = [
        'packaging' => ['confirmed' => 'To pack', 'packing' => 'Packing', 'packed' => 'Packed'],
        'warehouse' => ['packed' => 'Ready to dispatch', 'dispatched' => 'Left warehouse', 'out_for_delivery' => 'Out for delivery'],
        'delivery' => ['mine' => 'To deliver', 'delivered' => 'Delivered today'],
    ];

    public function mount(string $mode = 'packaging'): void
    {
        $this->mode = $mode;
        $this->tab = $this->tab && isset(self::TABS[$mode][$this->tab]) ? $this->tab : array_key_first(self::TABS[$mode]);
        $u = auth()->user();
        abort_unless($u->isSuperAdmin() || $u->role->value === $mode, 403);
        if ($u->hub_id) {
            $this->hub = $u->hub_id;
        }
    }

    public function setTab(string $t): void
    {
        $this->tab = $t;
    }

    public function move(int $orderId, string $to): void
    {
        $order = Order::findOrFail($orderId);
        $extra = ['note' => $this->note[$orderId] ?? null];
        if ($to === 'dispatched' && ! empty($this->agent[$orderId])) {
            $extra['agent_id'] = (int) $this->agent[$orderId];
        }
        if ($to === 'delivered') {
            $extra['otp'] = trim($this->otp[$orderId] ?? '');
        }
        try {
            app(OrderService::class)->transition($order, $to, auth()->user(), $extra);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: collect($e->errors())->flatten()->first(), kind: 'error');

            return;
        }
        unset($this->otp[$orderId], $this->note[$orderId]);
        $this->dispatch('toast', message: "{$order->number} → " . \App\Support\OrderStatus::label($to), kind: 'success');
    }

    private function query()
    {
        $q = Order::query();
        if ($this->hub) {
            $q->where('hub_id', $this->hub);
        }
        $u = auth()->user();

        return match ($this->mode) {
            'delivery' => $u->role === Role::Delivery ? $q->where('delivery_agent_id', $u->id) : $q,
            default => $q,
        };
    }

    public function render()
    {
        $base = $this->query();
        $counts = (clone $base)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $q = (clone $base)->with(['items', 'shop.user', 'hub', 'agent'])->withCount('items');

        if ($this->mode === 'delivery') {
            $q = $this->tab === 'delivered'
                ? $q->where('status', 'delivered')->whereDate('delivered_at', today())->orderByDesc('delivered_at')
                : $q->whereIn('status', ['dispatched', 'out_for_delivery'])->orderBy('dispatched_at');
        } elseif ($this->tab === 'packed' && $this->mode === 'packaging') {
            $q = $q->where('status', 'packed')->orderByDesc('packed_at')->limit(30);
        } else {
            $q = $q->where('status', $this->tab)->oldest('updated_at');
        }

        $agents = $this->mode === 'warehouse'
            ? User::where('role', Role::Delivery->value)->where('is_active', true)->when($this->hub, fn ($q) => $q->where('hub_id', $this->hub))
                ->withCount(['deliveries as load' => fn ($q) => $q->whereIn('status', ['dispatched', 'out_for_delivery'])])->get()
            : collect();

        return view('livewire.staff.order-queue', [
            'orders' => $q->get(),
            'tabs' => self::TABS[$this->mode],
            'counts' => $counts,
            'agents' => $agents,
            'hubs' => auth()->user()->hub_id ? collect() : Hub::orderBy('name')->get(),
            'titles' => ['packaging' => ['Packaging station', 'inventory_2'], 'warehouse' => ['Warehouse dispatch', 'warehouse'], 'delivery' => ['Deliveries', 'local_shipping']][$this->mode],
        ])->title(['packaging' => 'Packaging station', 'warehouse' => 'Warehouse', 'delivery' => 'Deliveries'][$this->mode]);
    }
}
