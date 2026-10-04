<div class="page stack-lg" wire:poll.20s>
  <div class="row between wrap"><div><h1 class="headline-m">Customer support</h1><div class="muted">New tickets are auto-assigned to the least-loaded agent.</div></div>
    <div class="row wrap">@foreach($load as $a)<span class="chip {{ $a->id === auth()->id() ? 'gold' : '' }}"><span class="material-symbols-outlined icon-sm">person</span>{{ $a->name }} · {{ $a->open_count }}</span>@endforeach</div></div>
  <div class="tabs">@foreach(['mine' => 'Assigned to me (' . $counts['mine'] . ')', 'unassigned' => 'Unassigned (' . $counts['unassigned'] . ')', 'open' => 'All open (' . $counts['open'] . ')', 'done' => 'Resolved / closed', 'all' => 'Everything'] as $k => $l)<button class="tab {{ $tab === $k ? 'active' : '' }}" wire:click="$set('tab','{{ $k }}')">{{ $l }}</button>@endforeach</div>
  <div class="grid cols-3"><div class="field"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Search subject, ticket # or shop</label></div>
    <x-m3.select label="Category" wire:model.live="category"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c['key'] }}">{{ $c['label'] }}</option>@endforeach</x-m3.select></div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Ticket</th><th>Shop</th><th>Category</th><th>Priority</th><th>Agent</th><th>Status</th><th>Last activity</th></tr></thead><tbody>
    @forelse($tickets as $t)
      <tr wire:key="t{{ $t->id }}"><td><a class="title-s" href="{{ route('staff.support.show', $t) }}" wire:navigate>{{ $t->subject }}</a><div class="muted">{{ $t->number }}@if($t->order) · {{ $t->order->number }}@endif</div></td>
        <td>{{ $t->user->shop?->shop_name }}<div class="muted">{{ $t->user->name }}</div></td><td>{{ $t->categoryLabel() }}</td>
        <td><span class="badge {{ $t->priority === 'high' ? 'error' : 'neutral' }}">{{ $t->priority }}</span></td><td>{{ $t->agent?->name ?? '—' }}</td>
        <td><span class="badge {{ ['open' => 'gold', 'in_progress' => 'primary', 'resolved' => 'success', 'closed' => 'neutral'][$t->status] }}">{{ str_replace('_', ' ', $t->status) }}</span></td><td class="muted nowrap">{{ $t->last_reply_at?->diffForHumans() }}</td></tr>
    @empty<tr><td colspan="7"><div class="empty"><span class="material-symbols-outlined">inbox</span><div>Inbox zero 🎉</div></div></td></tr>@endforelse
  </tbody></table></div></div>
  <x-m3.pager :p="$tickets" />
</div>
