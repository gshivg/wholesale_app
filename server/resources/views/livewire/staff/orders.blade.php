<div class="page stack-lg">
  <h1 class="headline-m">Orders</h1>
  <div class="row wrap">
    <button class="chip {{ $status === '' ? 'selected' : '' }}" wire:click="$set('status','')">All</button>
    @foreach(array_merge(\App\Support\OrderStatus::pipeline(), ['cancelled', 'returned']) as $s)<button class="chip {{ $status === $s ? 'selected' : '' }}" wire:click="$set('status','{{ $s }}')">{{ \App\Support\OrderStatus::label($s) }} · {{ $counts[$s] ?? 0 }}</button>@endforeach
  </div>
  <div class="grid cols-3">
    <div class="field"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Search order # or shop</label></div>
    <x-m3.select label="Hub" wire:model.live="hub"><option value="">All hubs</option>@foreach($hubs as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach</x-m3.select>
    <x-m3.select label="Payment" wire:model.live="payment"><option value="">Any</option>@foreach(config('shared.orders.paymentMethods') as $m)<option value="{{ $m }}">{{ strtoupper($m) }}</option>@endforeach</x-m3.select>
  </div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Order</th><th>Shop</th><th>Hub</th><th class="right">Cases</th><th class="right">Total</th><th>Payment</th><th>Status</th><th>Placed</th></tr></thead><tbody>
    @forelse($orders as $o)
      <tr wire:key="o{{ $o->id }}"><td><a class="title-s" href="{{ route('staff.orders.show', $o) }}" wire:navigate>{{ $o->number }}</a></td><td>{{ $o->shop->shop_name }}<div class="muted">{{ $o->shop->city }}</div></td><td>{{ $o->hub->code }}</td>
        <td class="right">{{ $o->total_cases }}</td><td class="right"><x-m3.money :v="$o->total" /></td><td>{{ strtoupper($o->payment_method) }} <span class="badge {{ $o->payment_status === 'paid' ? 'success' : 'neutral' }}">{{ $o->payment_status }}</span></td>
        <td><x-m3.status :status="$o->status" /></td><td class="muted nowrap">{{ $o->created_at->format('d M, h:i A') }}</td></tr>
    @empty<tr><td colspan="8"><div class="empty">No orders match.</div></td></tr>@endforelse
  </tbody></table></div></div>
  <x-m3.pager :p="$orders" />
</div>
