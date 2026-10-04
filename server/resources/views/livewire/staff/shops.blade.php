<div class="page stack-lg">
  <div><h1 class="headline-m">Shopkeepers</h1><div class="muted">Review registrations, set credit limits, suspend accounts.</div></div>
  <div class="row wrap">@foreach(['pending' => 'Awaiting approval', 'approved' => 'Approved', 'rejected' => 'Rejected', 'suspended' => 'Suspended', 'all' => 'All'] as $k => $l)<button class="chip {{ $status === $k ? ($k === 'pending' ? 'gold' : 'selected') : '' }}" wire:click="$set('status','{{ $k }}')">{{ $l }}@if($k !== 'all') · {{ $counts[$k] ?? 0 }}@endif</button>@endforeach</div>
  <div class="field" style="max-width:420px"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Search shop, owner, phone, PIN, GSTIN</label></div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Shop</th><th>Owner</th><th>Location</th><th>PAN / GSTIN</th><th>Hub</th><th>Status</th><th>Registered</th></tr></thead><tbody>
    @forelse($shops as $s)
      <tr wire:key="s{{ $s->id }}"><td><a class="title-s" href="{{ route('staff.shops.show', $s) }}" wire:navigate>{{ $s->shop_name }}</a><div class="muted">{{ $s->shop_type }}</div></td><td>{{ $s->user->name }}<div class="muted">{{ $s->user->phone }}</div></td>
        <td>{{ $s->city }} {{ $s->pincode }}</td><td>{{ $s->pan }}<div class="muted">{{ $s->gstin ?: '—' }}</div></td><td>{{ $s->hub?->code ?? '—' }}</td>
        <td><span class="badge {{ ['approved' => 'success', 'pending' => 'gold', 'rejected' => 'error', 'suspended' => 'error'][$s->status] }}">{{ $s->status }}</span>@unless($s->user->phone_verified_at)<div class="muted label-m">phone unverified</div>@endunless</td><td class="muted nowrap">{{ $s->created_at->format('d M Y') }}</td></tr>
    @empty<tr><td colspan="7"><div class="empty">Nothing here.</div></td></tr>@endforelse
  </tbody></table></div></div>
  <x-m3.pager :p="$shops" />
</div>
