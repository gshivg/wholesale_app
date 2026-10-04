<?php

namespace App\Livewire\Staff\Support;

use App\Enums\Role;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.staff')]
#[Title('Support tickets')]
class Queue extends Component
{
    use WithPagination;

    #[Url] public string $tab = 'mine';
    #[Url] public string $category = '';
    #[Url(as: 'q')] public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole(Role::Support, Role::SuperAdmin), 403);
        if (auth()->user()->isSuperAdmin() && $this->tab === 'mine') {
            $this->tab = 'open';
        }
    }

    public function updated(): void { $this->resetPage(); }

    public function render()
    {
        $me = auth()->id();
        $q = Ticket::with('user.shop', 'agent', 'order')->latest('last_reply_at');
        match ($this->tab) {
            'mine' => $q->where('assigned_to', $me)->whereIn('status', ['open', 'in_progress']),
            'unassigned' => $q->whereNull('assigned_to')->whereIn('status', ['open', 'in_progress']),
            'open' => $q->whereIn('status', ['open', 'in_progress']),
            'done' => $q->whereIn('status', ['resolved', 'closed']),
            default => null,
        };
        if ($this->category !== '') { $q->where('category', $this->category); }
        if ($this->search !== '') {
            $t = '%' . mb_strtolower($this->search) . '%';
            $q->where(fn ($w) => $w->whereRaw('lower(subject) like ?', [$t])->orWhere('number', 'ilike', $t)->orWhereHas('user.shop', fn ($s) => $s->whereRaw('lower(shop_name) like ?', [$t])));
        }
        $open = Ticket::whereIn('status', ['open', 'in_progress']);

        return view('livewire.staff.support.queue', [
            'tickets' => $q->paginate(15),
            'counts' => ['mine' => (clone $open)->where('assigned_to', $me)->count(), 'unassigned' => (clone $open)->whereNull('assigned_to')->count(), 'open' => (clone $open)->count()],
            'load' => User::where('role', Role::Support->value)->withCount(['assignedTickets as open_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress'])])->get(),
            'categories' => config('shared.support.categories'),
        ]);
    }
}
