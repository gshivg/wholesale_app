<?php

namespace App\Livewire\Portal;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Orders')]
class Orders extends Component
{
    use WithPagination;

    #[Url(except: 'all')]
    public string $filter = 'all';

    public function setFilter(string $f): void
    {
        $this->filter = $f;
        $this->resetPage();
    }

    public function render()
    {
        $q = auth()->user()->orders()->withCount('items')->latest();
        match ($this->filter) {
            'active' => $q->whereNotIn('status', ['delivered', 'cancelled', 'returned']),
            'delivered' => $q->where('status', 'delivered'),
            'closed' => $q->whereIn('status', ['cancelled', 'returned']),
            default => null,
        };

        return view('livewire.portal.orders', ['orders' => $q->paginate(10)]);
    }
}
