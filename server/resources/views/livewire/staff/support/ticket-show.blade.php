<div class="page stack-lg" wire:poll.10s>
  <div><a class="btn btn-text" href="{{ route('staff.support') }}" wire:navigate><span class="material-symbols-outlined">arrow_back</span>Tickets</a>
    <div class="row between wrap"><div><h1 class="headline-s">{{ $t->subject }}</h1><div class="muted">{{ $t->number }} · {{ $t->categoryLabel() }} · opened {{ $t->created_at->diffForHumans() }}</div></div>
      <div class="row"><span class="badge {{ $t->priority === 'high' ? 'error' : 'neutral' }}">{{ $t->priority }} priority</span><span class="badge {{ ['open' => 'gold', 'in_progress' => 'primary', 'resolved' => 'success', 'closed' => 'neutral'][$t->status] }}">{{ str_replace('_', ' ', $t->status) }}</span>@if($t->rating)<span class="badge gold">★ {{ $t->rating }}</span>@endif</div></div></div>

  <div class="grid" style="grid-template-columns:minmax(0,1.7fr) minmax(260px,1fr);align-items:start">
    <div class="stack">
      <div class="card"><x-m3.chat :messages="$messages" :viewer-is-staff="true" /></div>
      <form class="card stack" wire:submit="send">
        <div class="row wrap">@foreach($canned as $c)<button type="button" class="chip" wire:click="$set('body', @js($c))">{{ \Illuminate\Support\Str::limit($c, 34) }}</button>@endforeach</div>
        <x-m3.textarea label="{{ $internal ? 'Internal note (not visible to customer)' : 'Reply to customer' }}" wire:model="body" />
        <div class="row between wrap"><label class="check"><input type="checkbox" wire:model.live="internal"> Internal note</label><button class="btn {{ $internal ? 'btn-tonal' : 'btn-filled' }}" wire:loading.attr="disabled"><span class="material-symbols-outlined">{{ $internal ? 'lock' : 'send' }}</span>{{ $internal ? 'Save note' : 'Send reply' }}</button></div>
      </form>
    </div>
    <div class="stack">
      <div class="card stack">
        <h2 class="title-m">Status</h2>
        <div class="row wrap">@foreach(['in_progress' => 'In progress', 'resolved' => 'Resolve', 'closed' => 'Close', 'open' => 'Reopen'] as $s => $l)@if($t->status !== $s)<button class="btn btn-sm {{ $s === 'resolved' ? 'btn-gold' : 'btn-tonal' }}" wire:click="setStatus('{{ $s }}')">{{ $l }}</button>@endif @endforeach</div>
        <div class="divider"></div>
        <div class="kv"><span class="muted">Assigned to</span><b>{{ $t->agent?->name ?? 'Unassigned' }}</b></div>
        @if($t->assigned_to !== auth()->id())<button class="btn btn-outlined btn-sm" wire:click="claim">Assign to me</button>@endif
        <div class="row"><x-m3.select class="grow" label="Reassign" wire:model="assignee"><option value="">Unassigned</option>@foreach($agents as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</x-m3.select><button class="btn btn-tonal btn-sm" wire:click="reassign">Go</button></div>
        <div class="kv"><span class="muted">First response</span><span>{{ $t->first_response_at ? $t->first_response_at->diffForHumans($t->created_at, true) : 'waiting…' }}</span></div>
      </div>
      <div class="card stack-sm"><h2 class="title-m mb-2">Customer</h2>
        <div class="title-s">{{ $t->user->shop?->shop_name }}</div><div>{{ $t->user->name }} · <a href="tel:{{ $t->user->phone }}">{{ $t->user->phone }}</a></div><div class="muted">{{ $t->user->shop?->city }} · {{ $t->user->shop?->hub?->name }}</div>
        @if($t->user->shop && auth()->user()->isSuperAdmin())<a class="btn btn-text btn-sm" style="align-self:flex-start" href="{{ route('staff.shops.show', $t->user->shop) }}" wire:navigate>Open profile</a>@endif</div>
      <div class="card stack-sm"><h2 class="title-m mb-2">Recent orders</h2>
        @foreach($orders as $o)<div class="row between"><span class="@if($t->order_id === $o->id) title-s @endif">{{ $o->number }}@if($t->order_id === $o->id) 📌@endif</span><x-m3.status :status="$o->status" /></div>@endforeach
        @if($t->order)<div class="muted label-m">Linked order: {{ $t->order->number }} · total <x-m3.money :v="$t->order->total" /></div>@endif</div>
    </div>
  </div>
</div>
