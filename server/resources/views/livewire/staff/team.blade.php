<div class="page stack-lg">
  <div class="row between wrap"><div><h1 class="headline-m">Team</h1><div class="muted">Packaging, warehouse and delivery staff belong to one hub and only see that hub's orders.</div></div><button class="btn btn-gold" wire:click="create"><span class="material-symbols-outlined">person_add</span>Add staff</button></div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Name</th><th>Mobile</th><th>Role</th><th>Hub</th><th>Status</th><th></th></tr></thead><tbody>
    @foreach($staff as $u)<tr wire:key="u{{ $u->id }}"><td class="title-s">{{ $u->name }}</td><td>{{ $u->phone }}</td><td><span class="badge primary">{{ $u->role->label() }}</span></td><td>{{ $u->hub?->name ?? '—' }}</td>
      <td><span class="badge {{ $u->is_active ? 'success' : 'error' }}">{{ $u->is_active ? 'active' : 'disabled' }}</span></td><td class="right"><button class="btn btn-tonal btn-sm" wire:click="edit({{ $u->id }})">Edit</button></td></tr>@endforeach
  </tbody></table></div></div>
  @if($editing)<div class="scrim"><form class="dialog stack" wire:submit="save"><h2 class="headline-s">{{ $userId ? 'Edit staff' : 'Add staff' }}</h2>
    <x-m3.input label="Full name" wire:model="name" /><div class="grid cols-2"><x-m3.input label="Mobile (login)" wire:model="phone" maxlength="10" /><x-m3.input label="Email (optional)" wire:model="email" /></div>
    <div class="grid cols-2"><x-m3.select label="Role" wire:model.live="role">@foreach($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach</x-m3.select>
      @if(\App\Enums\Role::from($role)->isHubScoped())<x-m3.select label="Hub" wire:model="hub_id"><option value="">Select…</option>@foreach($hubs as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach</x-m3.select>@endif</div>
    <x-m3.input label="{{ $userId ? 'New password (leave blank to keep)' : 'Password' }}" type="password" wire:model="password" /><label class="check"><input type="checkbox" wire:model="is_active"> Active</label>
    <div class="actions"><button type="button" class="btn btn-text" wire:click="$set('editing', false)">Cancel</button><button class="btn btn-filled">Save</button></div></form></div>@endif
</div>
