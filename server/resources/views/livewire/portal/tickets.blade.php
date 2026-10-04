<div class="page stack-lg">
  <div class="row between wrap"><div><h1 class="headline-m">Help & support</h1><div class="muted">Damaged goods, missing items, delays, payments — raise a ticket and our team will respond.</div></div>
    <button class="btn btn-gold" wire:click="$toggle('creating')"><span class="material-symbols-outlined">add</span>New ticket</button></div>

  @if($creating)
    <form class="card stack" wire:submit="create">
      <h2 class="title-m">New ticket</h2>
      <div class="grid cols-2">
        <x-m3.select label="What's it about?" wire:model="category">@foreach($categories as $c)<option value="{{ $c['key'] }}">{{ $c['label'] }}</option>@endforeach</x-m3.select>
        <x-m3.select label="Related order (optional)" wire:model="order_id"><option value="">— none —</option>@foreach($orders as $o)<option value="{{ $o->id }}">{{ $o->number }}</option>@endforeach</x-m3.select>
      </div>
      <x-m3.input label="Subject" wire:model="subject" />
      <x-m3.textarea label="Describe the problem" wire:model="message" />
      <div><div class="label-l mb-2">Photos (optional, up to 4)</div><input type="file" wire:model="photos" multiple accept="image/*">@error('photos.*')<div class="error">{{ $message }}</div>@enderror
        <div class="row mt-2">@foreach($photos as $p)<img src="{{ $p->temporaryUrl() }}" style="height:64px;border-radius:8px">@endforeach</div></div>
      <div class="row" style="justify-content:flex-end"><button type="button" class="btn btn-text" wire:click="$set('creating', false)">Cancel</button><button class="btn btn-filled" wire:loading.attr="disabled">Submit ticket</button></div>
    </form>
  @endif

  <div class="card flat">
    @forelse($tickets as $t)
      <a class="list-item" style="color:inherit;text-decoration:none" href="{{ route('portal.tickets.show', $t) }}" wire:navigate wire:key="t{{ $t->id }}">
        <div class="lead"><span class="material-symbols-outlined">{{ $t->isOpen() ? 'chat' : 'task_alt' }}</span></div>
        <div class="grow"><div class="title-s">{{ $t->subject }}</div><div class="muted">{{ $t->number }} · {{ $t->categoryLabel() }} · {{ $t->last_reply_at?->diffForHumans() }}</div></div>
        <span class="badge {{ ['open' => 'gold', 'in_progress' => 'primary', 'resolved' => 'success', 'closed' => 'neutral'][$t->status] }}">{{ str_replace('_', ' ', $t->status) }}</span>
      </a>
    @empty
      <div class="empty"><span class="material-symbols-outlined">support_agent</span><div>No tickets yet — we hope it stays that way!</div></div>
    @endforelse
  </div>
</div>
