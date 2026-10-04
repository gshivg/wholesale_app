<?php

namespace App\Livewire\Staff;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Hub;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.staff')]
#[Title('Team')]
class Team extends Component
{
    public bool $editing = false;
    public ?int $userId = null;
    public string $name = '', $phone = '', $email = '', $password = '', $role = 'packaging';
    public ?int $hub_id = null;
    public bool $is_active = true;

    public function mount(): void { abort_unless(auth()->user()->isSuperAdmin(), 403); }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('userId', 'name', 'phone', 'email', 'password');
        $this->role = 'packaging'; $this->hub_id = Hub::where('type', 'main')->value('id'); $this->is_active = true; $this->editing = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $u = User::where('role', '!=', Role::Customer->value)->findOrFail($id);
        $this->fill(['userId' => $u->id, 'name' => $u->name, 'phone' => $u->phone, 'email' => (string) $u->email, 'role' => $u->role->value, 'hub_id' => $u->hub_id, 'is_active' => $u->is_active, 'password' => '']);
        $this->editing = true;
    }

    public function save(): void
    {
        $roles = collect(Role::cases())->reject(fn ($r) => $r === Role::Customer)->map->value->all();
        $this->validate([
            'name' => 'required|max:100', 'phone' => ['required', 'regex:/^[6-9]\d{9}$/', Rule::unique('users', 'phone')->ignore($this->userId)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($this->userId)], 'role' => ['required', Rule::in($roles)],
            'hub_id' => [Role::from($this->role)->isHubScoped() ? 'required' : 'nullable', 'exists:hubs,id'], 'password' => [$this->userId ? 'nullable' : 'required', 'min:8'],
        ]);
        if ($this->userId === auth()->id() && (! $this->is_active || $this->role !== 'super_admin')) {
            $this->addError('role', 'You cannot demote or disable your own account.');

            return;
        }
        $data = ['name' => $this->name, 'phone' => $this->phone, 'email' => $this->email ?: null, 'role' => $this->role, 'is_active' => $this->is_active,
            'hub_id' => Role::from($this->role)->isHubScoped() ? $this->hub_id : null, 'phone_verified_at' => now()];
        if ($this->password) { $data['password'] = $this->password; }
        $u = $this->userId ? tap(User::findOrFail($this->userId))->update($data) : User::create($data);
        if (! $u->is_active) { $u->tokens()->delete(); }
        AuditLog::record($this->userId ? 'staff.updated' : 'staff.created', $u, ['role' => $this->role]);
        $this->editing = false;
        $this->dispatch('toast', message: 'Saved', kind: 'success');
    }

    public function render()
    {
        return view('livewire.staff.team', [
            'staff' => User::where('role', '!=', Role::Customer->value)->with('hub')->orderBy('role')->orderBy('name')->get(),
            'hubs' => Hub::orderBy('name')->get(),
            'roles' => collect(Role::cases())->reject(fn ($r) => $r === Role::Customer),
        ]);
    }
}
