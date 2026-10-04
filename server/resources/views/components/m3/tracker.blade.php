{{-- Order progress stepper. Expects an Order model. --}}
@props(['order'])
@php
  $stages = config('shared.orders.stages');
  $cancelled = in_array($order->status, ['cancelled', 'returned']);
  $idx = array_search($order->status, array_column($stages, 'key'));
  $icons = ['placed' => 'receipt_long', 'confirmed' => 'verified', 'packing' => 'inventory_2', 'packed' => 'package_2', 'dispatched' => 'warehouse', 'out_for_delivery' => 'local_shipping', 'delivered' => 'home'];
@endphp
<div class="tracker {{ $cancelled ? 'cancelled' : '' }}">
  @foreach($stages as $i => $s)
    @php
      $at = $s['key'] === 'placed' ? $order->created_at : $order->{$s['key'].'_at'};
      $cls = $at ? ($idx !== false && $i === $idx && $s['key'] !== 'delivered' ? 'current' : 'done') : '';
    @endphp
    <div class="step {{ $cls }}">
      <div class="dot"><span class="material-symbols-outlined icon-sm">{{ $cls === 'done' ? 'check' : $icons[$s['key']] }}</span></div>
      <div class="t">{{ $s['label'] }}</div>
      <div class="d">{{ $at ? $at->format('d M, h:i A') : '—' }}</div>
    </div>
  @endforeach
</div>
@if($cancelled)<div class="banner error mt-2"><span class="material-symbols-outlined">cancel</span><div><b>Order {{ $order->status }}</b>@if($order->cancel_reason)<div>{{ $order->cancel_reason }}</div>@endif</div></div>@endif
