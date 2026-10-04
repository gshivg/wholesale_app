<div class="page stack-lg" style="max-width:800px">
  <div class="row between"><h1 class="headline-m">Notifications</h1><button class="btn btn-tonal btn-sm" wire:click="markAll">Mark all read</button></div>
  <div class="card flat">
    @forelse($items as $n)
      @php $icon = ['order' => 'local_shipping', 'ticket' => 'support_agent', 'account' => 'badge', 'offer' => 'local_offer'][$n->data['kind'] ?? ''] ?? 'notifications'; @endphp
      <button class="list-item w-full" style="border:0;background:{{ $n->read_at ? 'transparent' : 'var(--md-primary-container)' }};text-align:left;cursor:pointer;font:inherit;color:inherit" wire:click="open('{{ $n->id }}')" wire:key="n{{ $n->id }}">
        <div class="lead"><span class="material-symbols-outlined">{{ $icon }}</span></div>
        <div class="grow"><div class="title-s">{{ $n->data['title'] }}</div><div class="muted">{{ $n->data['body'] }}</div></div>
        <div class="muted label-m nowrap">{{ $n->created_at->diffForHumans(null, true, true) }}</div>
      </button>
    @empty
      <div class="empty"><span class="material-symbols-outlined">notifications_off</span><div>You're all caught up.</div></div>
    @endforelse
  </div>
</div>
