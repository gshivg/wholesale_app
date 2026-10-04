@php use App\Support\Money; $inr = fn ($v) => Money::fmt($v, true); @endphp
<div class="page stack-lg" wire:poll.30s>
  <div class="row between wrap"><div><h1 class="headline-m">Business overview</h1><div class="muted">Everything we can track, across all hubs.</div></div>
    <div class="row">@foreach([7 => '7 days', 30 => '30 days', 90 => '90 days'] as $d => $l)<button class="chip {{ $days === $d ? 'selected' : '' }}" wire:click="$set('days', {{ $d }})">{{ $l }}</button>@endforeach</div></div>

  <div class="grid cols-4">
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">currency_rupee</span></div><div class="v">{{ $inr($revenue) }}</div><div class="l">Revenue ({{ $days }}d)</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">receipt_long</span></div><div class="v">{{ $orderCount }}</div><div class="l">Orders · AOV {{ $inr($aov) }}</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">storefront</span></div><div class="v">{{ $activeShops }}</div><div class="l">Active shops · +{{ $newShops }} new</div></div>
    <a href="{{ route('staff.shops') }}" wire:navigate class="card stat-card stat" style="text-decoration:none"><div class="ic"><span class="material-symbols-outlined">how_to_reg</span></div><div class="v {{ $pendingShops ? 'gold' : '' }}">{{ $pendingShops }}</div><div class="l">Awaiting approval</div></a>
  </div>
  <div class="grid cols-4">
    <a href="{{ route('staff.support') }}" wire:navigate class="card stat-card stat" style="text-decoration:none"><div class="ic"><span class="material-symbols-outlined">support_agent</span></div><div class="v">{{ $ticketStats['open'] }}</div><div class="l">Open tickets · {{ $ticketStats['unassigned'] }} unassigned</div></a>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">warning</span></div><div class="v {{ $lowStock ? 'error' : '' }}">{{ $lowStock }}</div><div class="l">Low-stock SKUs (all hubs)</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">account_balance_wallet</span></div><div class="v">{{ $inr($creditOutstanding) }}</div><div class="l">Credit outstanding</div></div>
    <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">hourglass_empty</span></div><div class="v">{{ $unpaidOnline }}</div><div class="l">Online payments pending</div></div>
  </div>

  <div class="card">
    <div class="card-header"><h2 class="title-m">Revenue per day</h2><span class="muted">Peak {{ $inr($maxRev) }}</span></div>
    <div class="bars">@foreach($chart as $c)<div class="bar" title="{{ $c['label'] }}: {{ $inr($c['revenue']) }} · {{ $c['orders'] }} orders"><i style="height:{{ max(1, $c['revenue'] / $maxRev * 100) }}%"></i></div>@endforeach</div>
    <div class="row between muted label-m mt-2"><span>{{ $chart->first()['label'] }}</span><span>{{ $chart->last()['label'] }}</span></div>
  </div>

  <div class="grid cols-2" style="align-items:start">
    <div class="card"><h2 class="title-m mb-2">Orders by stage (all time)</h2>
      @php $max = max(1, $stageRows->max('count')); @endphp
      @foreach($stageRows as $r)<div class="hbar"><span>{{ $r['label'] }}</span><span class="track"><i style="width:{{ $r['count'] / $max * 100 }}%"></i></span><b class="right">{{ $r['count'] }}</b></div>@endforeach</div>
    <div class="card"><h2 class="title-m mb-2">Fulfilment speed (delivered orders)</h2>
      @if($ful && $ful->total_hours)
        <div class="kv"><span>Placed → confirmed</span><b>{{ round($ful->to_confirm) }} min</b></div>
        <div class="kv"><span>Confirmed → packed</span><b>{{ round($ful->to_pack) }} min</b></div>
        <div class="kv"><span>Packed → left warehouse</span><b>{{ round($ful->to_dispatch) }} min</b></div>
        <div class="kv"><span>Dispatched → delivered</span><b>{{ round($ful->to_deliver) }} min</b></div>
        <div class="kv total"><span>End to end</span><span>{{ round($ful->total_hours, 1) }} h</span></div>
      @else<div class="muted">No delivered orders in this period yet.</div>@endif
      <div class="divider"></div>
      <h2 class="title-m mb-2">Offers cost ({{ $days }}d)</h2>
      <div class="kv"><span>Product deals</span><b>{{ $inr($offers->deals) }}</b></div><div class="kv"><span>Volume slabs</span><b>{{ $inr($offers->slabs) }}</b></div><div class="kv"><span>Coupons ({{ $offers->coupon_orders }} orders)</span><b>{{ $inr($offers->coupons) }}</b></div>
    </div>
  </div>

  <div class="grid cols-2" style="align-items:start">
    <div class="card"><h2 class="title-m mb-2">Top products by revenue</h2>@php $m = max(1, $topProducts->max('revenue')); @endphp
      @foreach($topProducts as $p)<div class="hbar"><span title="{{ $p->name }}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $p->name }}</span><span class="track"><i style="width:{{ $p->revenue / $m * 100 }}%"></i></span><b class="right">{{ $inr($p->revenue) }}</b></div>@endforeach</div>
    <div class="card"><h2 class="title-m mb-2">Revenue by category</h2>@php $m = max(1, $categories->max('revenue')); @endphp
      @foreach($categories as $c)<div class="hbar"><span>{{ $c->name }}</span><span class="track"><i style="width:{{ $c->revenue / $m * 100 }}%"></i></span><b class="right">{{ $inr($c->revenue) }}</b></div>@endforeach</div>
    <div class="card"><h2 class="title-m mb-2">Top shopkeepers</h2>
      @foreach($topShops as $s)<div class="list-item"><div class="lead"><span class="material-symbols-outlined">storefront</span></div><div class="grow"><div class="title-s">{{ $s->shop_name }}</div><div class="muted">{{ $s->city }} · {{ $s->orders }} orders</div></div><b>{{ $inr($s->revenue) }}</b></div>@endforeach</div>
    <div class="card"><h2 class="title-m mb-2">Hubs & payment mix</h2>
      @foreach($byHub as $h)<div class="kv"><span>{{ $h->name }}</span><span><b>{{ $inr($h->revenue) }}</b> <span class="muted">· {{ $h->orders }} orders</span></span></div>@endforeach
      <div class="divider"></div>
      @foreach($byPayment as $p)<div class="kv"><span>{{ strtoupper($p->payment_method) }}</span><span><b>{{ $inr($p->s) }}</b> <span class="muted">· {{ $p->c }}</span></span></div>@endforeach</div>
  </div>

  <div class="grid cols-2" style="align-items:start">
    <div class="card"><h2 class="title-m mb-2">Support performance ({{ $days }}d)</h2>
      <div class="kv"><span>New tickets</span><b>{{ $ticketStats['new'] }}</b></div>
      <div class="kv"><span>Avg first response</span><b>{{ $ticketStats['avg_first_response'] !== null ? round($ticketStats['avg_first_response']) . ' min' : '—' }}</b></div>
      <div class="kv"><span>Avg resolution</span><b>{{ $ticketStats['avg_resolution'] !== null ? round($ticketStats['avg_resolution'], 1) . ' h' : '—' }}</b></div>
      <div class="kv"><span>Customer rating</span><b>{{ $ticketStats['csat'] ? number_format($ticketStats['csat'], 1) . ' / 5' : '—' }}</b></div>
      <div class="divider"></div>@foreach($ticketStats['by_category'] as $cat => $c)<div class="kv"><span>{{ collect(config('shared.support.categories'))->firstWhere('key', $cat)['label'] ?? $cat }}</span><b>{{ $c }}</b></div>@endforeach</div>
    <div class="card"><div class="card-header"><h2 class="title-m">Recent activity</h2><a class="btn btn-text btn-sm" href="{{ route('staff.audit') }}" wire:navigate>Audit log</a></div>
      @foreach($recent as $a)<div class="list-item"><div class="grow"><div class="title-s">{{ $a->action }}</div><div class="muted">{{ $a->user?->name ?? 'system' }} · {{ $a->created_at->diffForHumans() }}</div></div></div>@endforeach</div>
  </div>
</div>
