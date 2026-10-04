<?php

namespace App\Livewire\Staff;

use App\Enums\Role;
use App\Models\Shop;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.staff')]
#[Title('Shopkeepers')]
class Shops extends Component
{
    use WithPagination;

    #[Url] public string $status = 'pending';
    #[Url(as: 'q')] public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole(Role::SuperAdmin, Role::OrderManager), 403);
    }

    public function updated(): void { $this->resetPage(); }

    public function render()
    {
        $q = Shop::with('user', 'hub')->latest();
        if ($this->status !== 'all') { $q->where('status', $this->status); }
        if ($this->search !== '') {
            $t = '%' . mb_strtolower($this->search) . '%';
            $q->where(fn ($w) => $w->whereRaw('lower(shop_name) like ?', [$t])->orWhere('pincode', 'like', $t)->orWhere('gstin', 'like', strtoupper($t))->orWhereHas('user', fn ($u) => $u->where('phone', 'like', $t)->orWhereRaw('lower(name) like ?', [$t])));
        }

        return view('livewire.staff.shops', ['shops' => $q->paginate(15), 'counts' => Shop::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')]);
    }
}
