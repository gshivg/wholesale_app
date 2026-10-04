<?php

namespace App\Livewire\Staff;

use App\Models\Hub;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.staff')]
#[Title('Orders')]
class Orders extends Component
{
    use WithPagination;

    #[Url] public string $status = '';
    #[Url] public string $hub = '';
    #[Url] public string $payment = '';
    #[Url(as: 'q')] public string $search = '';

    public function updated($name): void { $this->resetPage(); }

    public function render()
    {
        $q = Order::with('shop', 'hub')->withCount('items')->latest();
        if ($this->status !== '') { $q->where('status', $this->status); }
        if ($this->hub !== '') { $q->where('hub_id', $this->hub); }
        if ($this->payment !== '') { $q->where('payment_method', $this->payment); }
        if ($this->search !== '') {
            $t = '%' . mb_strtolower($this->search) . '%';
            $q->where(fn ($w) => $w->whereRaw('lower(number) like ?', [$t])->orWhereHas('shop', fn ($s) => $s->whereRaw('lower(shop_name) like ?', [$t])));
        }
        $counts = Order::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');

        return view('livewire.staff.orders', ['orders' => $q->paginate(15), 'hubs' => Hub::orderBy('name')->get(), 'counts' => $counts]);
    }
}
