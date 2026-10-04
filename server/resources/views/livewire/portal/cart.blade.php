@php use App\Support\Money; $t = $quote['totals']; @endphp
<div class="page stack-lg">
  <h1 class="headline-m">Your cart</h1>
  @if(! count($quote['lines']))
    <div class="card empty"><span class="material-symbols-outlined">shopping_cart</span><div class="title-m">Your cart is empty</div><a class="btn btn-gold mt-3" href="{{ route('portal.catalog') }}" wire:navigate>Browse products</a></div>
  @else
  <div class="grid" style="grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);align-items:start">
    <div class="stack">
      <div class="card flat">
        @foreach($quote['lines'] as $l)
          <div class="list-item" style="align-items:flex-start" wire:key="l{{ $l['variant_id'] }}">
            <div class="lead" style="border-radius:12px;width:48px;height:48px;background:{{ $l['variant']->product->accent ?? '#1B3A8C' }};color:#fff"><span class="material-symbols-outlined">{{ $l['variant']->product->category->icon ?? 'cookie' }}</span></div>
            <div class="grow stack-sm">
              <div class="title-s">{{ $l['name'] }} <span class="muted">· {{ $l['label'] }}</span></div>
              <div class="muted">{{ Money::fmt($l['unit']) }} / case · GST {{ rtrim(rtrim((string) $l['gst_rate'], '0'), '.') }}%</div>
              @if($l['deal'] > 0)<span class="badge gold">{{ $l['deal_title'] }} · −{{ Money::fmt($l['deal']) }}</span>@endif
              @if($l['free_qty'] > 0)<span class="badge gold">🎁 {{ $l['free_qty'] }} free case(s) — {{ $l['deal_title'] }}</span>@endif
              @foreach($quote['errors'] as $e)@if(($e['variant_id'] ?? null) === $l['variant_id'])<div class="error label-m">{{ $e['message'] }}</div>@endif @endforeach
            </div>
            <div class="stack-sm" style="align-items:flex-end">
              <div class="qty"><button wire:click="step({{ $l['variant_id'] }}, -1)">−</button><input type="number" value="{{ $l['qty'] }}" wire:change="setQty({{ $l['variant_id'] }}, $event.target.value)"><button wire:click="step({{ $l['variant_id'] }}, 1)">+</button></div>
              <div class="title-s">{{ Money::fmt($l['net']) }}</div>
              <button class="btn btn-danger-text btn-sm" wire:click="setQty({{ $l['variant_id'] }}, 0)"><span class="material-symbols-outlined">delete</span>Remove</button>
            </div>
          </div>
        @endforeach
      </div>

      @if($quote['next_slab'])
        @php $ns = $quote['next_slab']; $pct = min(100, $t['subtotal'] / max(1, $ns['min_subtotal'] * 100) * 100); @endphp
        <div class="card gold stack-sm"><div class="row"><span class="material-symbols-outlined">savings</span><b>Add {{ Money::fmt($ns['remaining']) }} more to unlock {{ rtrim(rtrim((string) $ns['percent'], '0'), '.') }}% off</b></div><div class="progress"><i style="width:{{ $pct }}%"></i></div></div>
      @elseif($t['slab_percent'] > 0)
        <div class="banner success"><span class="material-symbols-outlined">verified</span><div><b>Top volume discount unlocked: {{ rtrim(rtrim((string) $t['slab_percent'], '0'), '.') }}% off!</b></div></div>
      @endif

      <div class="card stack">
        <h2 class="title-m">Coupon</h2>
        @if($quote['coupon']['applied'])
          <div class="row between"><div class="row"><span class="chip gold"><span class="material-symbols-outlined icon-sm">sell</span>{{ $quote['coupon']['code'] }}</span><span class="success">saves {{ Money::fmt($quote['coupon']['discount']) }}</span></div><button class="btn btn-text" wire:click="removeCoupon">Remove</button></div>
        @else
          <form class="row" wire:submit="applyCoupon()"><x-m3.input class="grow" label="Have a code?" wire:model="coupon" style="text-transform:uppercase" /><button class="btn btn-tonal">Apply</button></form>
          @error('coupon')<div class="error label-m">{{ $message }}</div>@enderror
          <div class="row wrap">@foreach($available as $c)<button class="chip" wire:click="applyCoupon('{{ $c->code }}')" title="{{ $c->description }}"><span class="material-symbols-outlined icon-sm">sell</span>{{ $c->code }} · {{ $c->summary() }}</button>@endforeach</div>
        @endif
      </div>
    </div>

    <div class="stack" style="position:sticky;top:88px">
      <div class="card stack-sm">
        <h2 class="title-m mb-2">Order summary</h2>
        <div class="kv"><span>Items ({{ $t['total_cases'] }} cases)</span><span>{{ Money::fmt($t['gross']) }}</span></div>
        @if($t['deal_discount'])<div class="kv success"><span>Deals</span><span>−{{ Money::fmt($t['deal_discount']) }}</span></div>@endif
        @if($t['slab_discount'])<div class="kv success"><span>Volume discount ({{ rtrim(rtrim((string) $t['slab_percent'], '0'), '.') }}%)</span><span>−{{ Money::fmt($t['slab_discount']) }}</span></div>@endif
        @if($t['coupon_discount'])<div class="kv success"><span>Coupon {{ $quote['coupon']['code'] }}</span><span>−{{ Money::fmt($t['coupon_discount']) }}</span></div>@endif
        <div class="kv"><span>Taxable value</span><span>{{ Money::fmt($t['taxable']) }}</span></div>
        @if($t['cgst'])<div class="kv muted"><span>CGST</span><span>{{ Money::fmt($t['cgst']) }}</span></div><div class="kv muted"><span>SGST</span><span>{{ Money::fmt($t['sgst']) }}</span></div>@endif
        @if($t['igst'])<div class="kv muted"><span>IGST</span><span>{{ Money::fmt($t['igst']) }}</span></div>@endif
        @if($t['round_off'])<div class="kv muted"><span>Round off</span><span>{{ Money::fmt($t['round_off']) }}</span></div>@endif
        <div class="kv total"><span>Total</span><span>{{ Money::fmt($t['total']) }}</span></div>
        @if($t['savings'])<div class="center success label-l">You save {{ Money::fmt($t['savings']) }} 🎉</div>@endif
      </div>

      <div class="card stack">
        <h2 class="title-m">Payment</h2>
        <label class="option"><input type="radio" wire:model.live="payment" value="cod"><div><div class="title-s">Cash on delivery</div><div class="muted">Pay our driver when you receive the goods</div></div></label>
        <label class="option"><input type="radio" wire:model.live="payment" value="online"><div><div class="title-s">Pay online (sandbox)</div><div class="muted">Card or UPI — test payments only</div></div></label>
        <label class="option"><input type="radio" wire:model.live="payment" value="credit" @disabled($shop->credit_limit <= 0)><div><div class="title-s">Pay later (credit)</div><div class="muted">{{ $shop->credit_limit > 0 ? 'Available: ' . Money::fmt($shop->credit_available, true) : 'Not enabled for your account' }}</div></div></label>
        <x-m3.textarea label="Delivery notes (optional)" wire:model="notes" />
      </div>

      @foreach($quote['errors'] as $e)@if(! isset($e['variant_id']))<div class="banner error"><span class="material-symbols-outlined">error</span><div>{{ $e['message'] }}</div></div>@endif @endforeach
      <button class="btn btn-gold btn-lg btn-block" wire:click="place" wire:loading.attr="disabled" @disabled(! $quote['can_checkout'])><span class="material-symbols-outlined">lock</span>Place order · {{ Money::fmt($t['total']) }}</button>
      <div class="muted center label-m">Min order {{ Money::fmt($quote['minimums']['order_value'], true) }} and {{ $quote['minimums']['total_cases'] }} cases</div>
    </div>
  </div>
  @endif
</div>
