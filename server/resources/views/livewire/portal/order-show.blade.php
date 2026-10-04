@php use App\Support\Money; @endphp
<div class="page stack-lg" wire:poll.15s>
  <div class="row between wrap">
    <div><a class="btn btn-text" href="{{ route('portal.orders') }}" wire:navigate><span class="material-symbols-outlined">arrow_back</span>Orders</a>
      <h1 class="headline-m">{{ $o->number }}</h1><div class="muted">Placed {{ $o->created_at->format('d M Y, h:i A') }} · {{ strtoupper($o->payment_method) }} · payment <b>{{ $o->payment_status }}</b></div></div>
    <x-m3.status :status="$o->status" />
  </div>

  <div class="card"><x-m3.tracker :order="$o" /></div>

  @if(in_array($o->status, ['dispatched', 'out_for_delivery']))
    <div class="card gold row between wrap">
      <div class="row"><span class="material-symbols-outlined icon-lg">local_shipping</span><div><div class="title-m">{{ $o->status === 'out_for_delivery' ? 'Your order is out for delivery' : 'Your parcel has left the warehouse' }}</div>
        @if($o->agent)<div>Delivery partner: <b>{{ $o->agent->name }}</b> · <a href="tel:{{ $o->agent->phone }}">{{ $o->agent->phone }}</a></div>@endif</div></div>
      <div class="center"><div class="label-m">DELIVERY OTP — share with the driver</div><div class="otp-box">{{ $o->delivery_otp }}</div></div>
    </div>
  @endif

  <div class="grid" style="grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);align-items:start">
    <div class="stack">
      <div class="card flat">
        <div class="card-header" style="padding:16px 16px 0"><h2 class="title-m">Items</h2></div>
        <div class="table-wrap"><table class="data"><thead><tr><th>Item</th><th class="right">Cases</th><th class="right">Rate</th><th class="right">GST</th><th class="right">Total</th></tr></thead><tbody>
          @foreach($o->items as $i)
            <tr><td><div class="title-s">{{ $i->name }}</div><div class="muted">{{ $i->label }}@if($i->free_quantity) · <span class="gold">+{{ $i->free_quantity }} free</span>@endif</div></td>
              <td class="right">{{ $i->quantity }}</td><td class="right">{{ Money::fmt($i->unit_price, true) }}</td><td class="right">{{ rtrim(rtrim($i->gst_rate, '0'), '.') }}%</td><td class="right">{{ Money::fmt($i->total, true) }}</td></tr>
          @endforeach</tbody></table></div>
      </div>
      <div class="card"><h2 class="title-m mb-2">Timeline</h2><div class="timeline">
        @foreach($o->events->reverse() as $e)<div class="ev"><div class="title-s">{{ \App\Support\OrderStatus::label($e->status) }}</div><div class="muted">{{ $e->created_at->format('d M Y, h:i A') }}@if($e->note) · {{ $e->note }}@endif</div></div>@endforeach
      </div></div>
    </div>

    <div class="stack">
      <div class="card stack-sm">
        <h2 class="title-m mb-2">Payment summary</h2>
        <div class="kv"><span>Gross</span><span>{{ Money::fmt($o->gross_amount, true) }}</span></div>
        @if($o->deal_discount > 0)<div class="kv success"><span>Deals</span><span>−{{ Money::fmt($o->deal_discount, true) }}</span></div>@endif
        @if($o->slab_discount > 0)<div class="kv success"><span>Volume discount</span><span>−{{ Money::fmt($o->slab_discount, true) }}</span></div>@endif
        @if($o->coupon_discount > 0)<div class="kv success"><span>Coupon {{ $o->coupon_code }}</span><span>−{{ Money::fmt($o->coupon_discount, true) }}</span></div>@endif
        <div class="kv"><span>Taxable</span><span>{{ Money::fmt($o->taxable_amount, true) }}</span></div>
        @if($o->cgst > 0)<div class="kv muted"><span>CGST</span><span>{{ Money::fmt($o->cgst, true) }}</span></div><div class="kv muted"><span>SGST</span><span>{{ Money::fmt($o->sgst, true) }}</span></div>@endif
        @if($o->igst > 0)<div class="kv muted"><span>IGST</span><span>{{ Money::fmt($o->igst, true) }}</span></div>@endif
        <div class="kv total"><span>Total</span><span>{{ Money::fmt($o->total, true) }}</span></div>
      </div>
      <div class="card stack-sm"><h2 class="title-m mb-2">Delivery address</h2>
        <div>{{ $o->shipping_address['name'] }}</div><div class="muted">{{ $o->shipping_address['line1'] }} {{ $o->shipping_address['line2'] }}<br>{{ $o->shipping_address['city'] }} {{ $o->shipping_address['pincode'] }}</div>
        <div class="muted">Dispatched from {{ $o->hub->name }}</div></div>
      <div class="stack">
        @if($canPay)<button class="btn btn-gold btn-block" wire:click="startPayment"><span class="material-symbols-outlined">payments</span>Pay {{ Money::fmt($o->total, true) }} now</button>@endif
        @if($invoiceUrl)<a class="btn btn-filled btn-block" href="{{ $invoiceUrl }}" target="_blank"><span class="material-symbols-outlined">picture_as_pdf</span>Download GST invoice</a>@endif
        <button class="btn btn-tonal btn-block" wire:click="reorder"><span class="material-symbols-outlined">replay</span>Reorder these items</button>
        <a class="btn btn-outlined btn-block" href="{{ route('portal.tickets', ['order' => $o->id]) }}" wire:navigate><span class="material-symbols-outlined">support_agent</span>Report a problem</a>
        @if($o->isCustomerCancellable())<button class="btn btn-danger-text btn-block" wire:click="$set('confirmCancel', true)">Cancel order</button>@endif
      </div>
    </div>
  </div>

  @if($confirmCancel)
    <div class="scrim"><div class="dialog"><h2 class="headline-s mb-3">Cancel this order?</h2><div class="muted mb-3">Stock is released immediately. Any online payment will be refunded.</div>
      <x-m3.textarea label="Reason (optional)" wire:model="reason" />
      <div class="actions"><button class="btn btn-text" wire:click="$set('confirmCancel', false)">Keep order</button><button class="btn btn-danger" wire:click="cancel">Cancel order</button></div></div></div>
  @endif

  @if($paymentId)
    <div class="scrim"><div class="dialog stack">
      <div class="row between"><h2 class="headline-s">Sandbox payment</h2><span class="badge gold">TEST MODE</span></div>
      <div class="title-l primary">{{ Money::fmt($o->total, true) }}</div>
      <div class="row"><button class="chip {{ $method === 'card' ? 'selected' : '' }}" wire:click="$set('method','card')">Card</button><button class="chip {{ $method === 'upi' ? 'selected' : '' }}" wire:click="$set('method','upi')">UPI</button></div>
      <x-m3.input label="{{ $method === 'card' ? 'Card number' : 'UPI ID' }}" wire:model="instrument" />
      <div class="row wrap">
        <button class="chip" wire:click="useInstrument('card','4111 1111 1111 1111')">✔ Success card</button><button class="chip" wire:click="useInstrument('card','4000 0000 0000 0002')">✖ Failing card</button>
        <button class="chip" wire:click="useInstrument('upi','success@sandbox')">✔ success@sandbox</button><button class="chip" wire:click="useInstrument('upi','failure@sandbox')">✖ failure@sandbox</button>
      </div>
      <div class="muted label-m">No real money moves. Any expiry/CVV works.</div>
      <div class="actions"><button class="btn btn-text" wire:click="closePayment">Later</button><button class="btn btn-gold" wire:click="confirmPayment" wire:loading.attr="disabled">Pay now</button></div>
    </div></div>
  @endif
</div>
