<div class="page stack-lg">
  <h1 class="headline-m">My orders</h1>
  <div class="row wrap">@foreach(['all' => 'All', 'active' => 'In progress', 'delivered' => 'Delivered', 'closed' => 'Cancelled / returned'] as $k => $l)<button class="chip {{ $filter === $k ? 'selected' : '' }}" wire:click="setFilter('{{ $k }}')">{{ $l }}</button>@endforeach</div>
  <div class="stack">
    @forelse($orders as $o)
      <a class="card" style="color:inherit;text-decoration:none" href="{{ route('portal.orders.show', $o) }}" wire:navigate wire:key="o{{ $o->id }}">
        <div class="row between wrap"><div><div class="title-m">{{ $o->number }}</div><div class="muted">{{ $o->created_at->format('d M Y, h:i A') }} · {{ $o->items_count }} items · {{ $o->total_cases }} cases · {{ strtoupper($o->payment_method) }}</div></div>
          <div class="right"><div class="title-m"><x-m3.money :v="$o->total" /></div><x-m3.status :status="$o->status" /></div></div>
        @unless(in_array($o->status, ['cancelled', 'returned']))<div class="mt-2"><x-m3.tracker :order="$o" /></div>@endunless
      </a>
    @empty
      <div class="card empty"><span class="material-symbols-outlined">receipt_long</span><div>No orders here yet.</div></div>
    @endforelse
  </div>
  <x-m3.pager :p="$orders" />
</div>
