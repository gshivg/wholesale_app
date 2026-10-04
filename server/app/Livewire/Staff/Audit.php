<?php

namespace App\Livewire\Staff;

use App\Models\AuditLog;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.staff')]
#[Title('Audit log')]
class Audit extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';

    public function mount(): void { abort_unless(auth()->user()->isSuperAdmin(), 403); }
    public function updatedSearch(): void { $this->resetPage(); }

    public function render()
    {
        $q = AuditLog::with('user')->latest('id');
        if ($this->search !== '') { $q->where('action', 'ilike', "%{$this->search}%"); }

        return view('livewire.staff.audit', ['logs' => $q->paginate(25)]);
    }
}
