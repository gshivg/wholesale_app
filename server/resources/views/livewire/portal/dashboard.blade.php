<div class="page stack-lg">
  <div>
    <h1 class="headline-m">Namaste, {{ explode(' ', auth()->user()->name)[0] }} 👋</h1>
    <div class="muted">{{ $shop->shop_name }} · {{ $shop->city }} · served by <b>{{ $shop->hub?->name ?? 'no hub yet' }}</b></div>
  </div>

  @if($shop->status === 'pending')
    <div class="banner warn"><span class="material-symbols-outlined">hourglass_top</span><div><b>Your account is awaiting approval.</b><div>Our team is verifying your PAN{{ $shop->gstin ? ' & GSTIN' : '' }}. You can browse the catalogue now; ordering unlocks once approved.</div></div></div>
  @elseif($shop->status === 'rejected')
    <div class="banner error"><span class="material-symbols-outlined">block</span><div><b>Registration not approved.</b><div>{{ $shop->status_note ?: 'Please contact support for details.' }}</div></div></div>
  @elseif($shop->status === 'suspended')
    <div class="banner error"><span class="material-symbols-outlined">pause_circle</span><div><b>Account suspended.</b><div>{{ $shop->status_note ?: 'Please contact support.' }}</div></div></div>
  @endif

  <div class="grid cols-4">
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">local_shipping</span></div><div class="v">{{ $openCount }}</div><div class="l">Orders in progress</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">task_alt</span></div><div class="v">{{ $deliveredCount }}</div><div class="l">Delivered</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">payments</span></div><div class="v"><x-m3.money :v="$monthSpend" /></div><div class="l">This month</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">credit_score</span></div><div class="v"><x-m3.money :v="$shop->credit_available" /></div><div class="l">Credit available</div></div>
  </div>

  @if($active->count())
    <section class="stack">
      <div class="row between"><h2 class="title-l">Live deliveries</h2><a class="btn btn-text" href="{{ route('portal.orders') }}" wire:navigate>All orders</a></div>
      @foreach($active as $o)
        <a href="{{ route('portal.orders.show', $o) }}" wire:navigate class="card" style="color:inherit;text-decoration:none">
          <div class="row between wrap mb-2"><div><span class="title-m">{{ $o->number }}</span> <span class="muted">· {{ $o->total_cases }} cases · <x-m3.money :v="$o->total" /></span></div><x-m3.status :status="$o->status" /></div>
          <x-m3.tracker :order="$o" />
        </a>
      @endforeach
    </section>
  @endif

  <section class="grid cols-2">
    <div class="card stack">
      <div class="card-header"><h2 class="title-m">Offers for you</h2><a class="btn btn-text btn-sm" href="{{ route('portal.offers') }}" wire:navigate>See all</a></div>
      @foreach($coupons as $c)
        <div class="row"><span class="chip gold">{{ $c->code }}</span><div class="grow"><div class="title-s">{{ $c->title }}</div><div class="muted">{{ $c->summary() }}</div></div></div>
      @endforeach
      @foreach($deals as $d)
        <div class="row"><span class="badge primary">DEAL</span><div class="grow"><div class="title-s">{{ $d->title }}</div><div class="muted">{{ $d->summary() }}</div></div></div>
      @endforeach
      <div class="banner info"><span class="material-symbols-outlined">savings</span><div>Volume discount: <b>2%</b> over ₹10,000, <b>4%</b> over ₹25,000, <b>6%</b> over ₹50,000.</div></div>
    </div>
    <div class="card stack">
      <div class="card-header"><h2 class="title-m">Recent orders</h2></div>
      @forelse($recent as $o)
        <a class="list-item" style="color:inherit;text-decoration:none" href="{{ route('portal.orders.show', $o) }}" wire:navigate>
          <div class="lead"><span class="material-symbols-outlined">receipt_long</span></div>
          <div class="grow"><div class="title-s">{{ $o->number }}</div><div class="muted">{{ $o->created_at->format('d M Y') }} · {{ $o->items_count }} items</div></div>
          <div class="right"><x-m3.money :v="$o->total" /><div><x-m3.status :status="$o->status" /></div></div>
        </a>
      @empty
        <div class="empty"><span class="material-symbols-outlined">shopping_bag</span><div>No orders yet</div>@if($shop->status === 'approved')<a class="btn btn-gold mt-3" href="{{ route('portal.catalog') }}" wire:navigate>Start shopping</a>@endif</div>
      @endforelse
      @if($openTickets)<a class="banner warn" style="text-decoration:none" href="{{ route('portal.tickets') }}" wire:navigate><span class="material-symbols-outlined">support_agent</span><div>{{ $openTickets }} open support ticket(s)</div></a>@endif
    </div>
  </section>
</div>
