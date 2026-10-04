@php use App\Support\Money; use App\Support\OrderStatus; @endphp
<div class="page stack-lg">
  <div class="row between wrap">
    <div><a class="btn btn-text" href="{{ route('staff.orders') }}" wire:navigate><span class="material-symbols-outlined">arrow_back</span>Orders</a>
      <h1 class="headline-m">{{ $o->number }}</h1><div class="muted">{{ $o->shop->shop_name }} · {{ $o->hub->name }} · placed {{ $o->created_at->format('d M Y, h:i A') }}</div></div>
    <x-m3.status :status="$o->status" />
  </div>
  <div class="card"><x-m3.tracker :order="$o" /></div>

  <div class="card stack">
    <h2 class="title-m">Actions</h2>
    <x-m3.input label="Note (optional, saved to the timeline)" wire:model="note" />
    <div class="row wrap">
      @if($canAdvance)<button class="btn btn-gold" wire:click="move('{{ $next }}')"><span class="material-symbols-outlined">arrow_forward</span>{{ $next === 'confirmed' ? 'Confirm order' : 'Move to: ' . OrderStatus::label($next) }}</button>@endif
      @if($next && ! $canAdvance)<span class="chip">Next: {{ OrderStatus::label($next) }} — handled by {{ str_replace('_', ' ', $stageRole) }}</span>@endif
      @if($o->payment_status !== 'paid' && ! in_array($o->status, ['cancelled', 'returned']))<button class="btn btn-tonal" wire:click="markPaid" wire:confirm="Mark this order's payment as received?"><span class="material-symbols-outlined">payments</span>Mark payment received</button>@endif
      @if($canReturn)<button class="btn btn-outlined" wire:click="move('returned')" wire:confirm="Mark as returned and restock?">Mark returned</button>@endif
      @if($canCancel)<button class="btn btn-danger-text" wire:click="$set('confirmCancel', true)">Cancel order</button>@endif
    </div>
    @if(auth()->user()->isSuperAdmin() && in_array($o->status, ['dispatched', 'out_for_delivery']))
      <div class="row"><x-m3.select class="grow" label="Delivery agent" wire:model="agentId"><option value="">—</option>@foreach($agents as $a)<option value="{{ $a->id }}" @selected($o->delivery_agent_id === $a->id)>{{ $a->name }}</option>@endforeach</x-m3.select><button class="btn btn-tonal" wire:click="reassign">Reassign</button></div>
    @endif
  </div>

  <div class="grid" style="grid-template-columns:minmax(0,1.5fr) minmax(280px,1fr);align-items:start">
    <div class="stack">
      <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Item</th><th class="right">Cases</th><th class="right">Rate</th><th class="right">Taxable</th><th class="right">GST</th><th class="right">Total</th></tr></thead><tbody>
        @foreach($o->items as $i)<tr><td><div class="title-s">{{ $i->name }}</div><div class="muted">{{ $i->label }} · {{ $i->sku }}@if($i->free_quantity) · <span class="gold">+{{ $i->free_quantity }} free</span>@endif @if($i->deal_title) · {{ $i->deal_title }}@endif</div></td>
          <td class="right">{{ $i->quantity }}</td><td class="right">{{ Money::fmt($i->unit_price, true) }}</td><td class="right">{{ Money::fmt($i->taxable, true) }}</td><td class="right">{{ Money::fmt($i->tax, true) }} <span class="muted">({{ rtrim(rtrim($i->gst_rate, '0'), '.') }}%)</span></td><td class="right">{{ Money::fmt($i->total, true) }}</td></tr>@endforeach
      </tbody></table></div></div>
      <div class="card"><h2 class="title-m mb-2">Timeline</h2><div class="timeline">@foreach($o->events->reverse() as $e)<div class="ev"><div class="title-s">{{ OrderStatus::label($e->status) }}</div><div class="muted">{{ $e->created_at->format('d M Y, h:i A') }} · {{ $e->user?->name ?? 'system' }}@if($e->note) · {{ $e->note }}@endif</div></div>@endforeach</div></div>
    </div>
    <div class="stack">
      <div class="card stack-sm"><h2 class="title-m mb-2">Customer</h2>
        <div class="title-s"><a href="{{ route('staff.shops.show', $o->shop) }}" wire:navigate>{{ $o->shop->shop_name }}</a></div><div>{{ $o->shop->user->name }} · <a href="tel:{{ $o->shop->user->phone }}">{{ $o->shop->user->phone }}</a></div>
        <div class="muted">{{ $o->shipping_address['line1'] }}, {{ $o->shipping_address['city'] }} {{ $o->shipping_address['pincode'] }}</div>
        <div class="muted">GSTIN: {{ $o->shop->gstin ?: 'unregistered' }}</div>@if($o->notes)<div class="banner warn mt-2"><span class="material-symbols-outlined">sticky_note_2</span><div>{{ $o->notes }}</div></div>@endif</div>
      <div class="card stack-sm"><h2 class="title-m mb-2">Totals</h2>
        <div class="kv"><span>Gross</span><span>{{ Money::fmt($o->gross_amount, true) }}</span></div>
        @if($o->deal_discount > 0)<div class="kv"><span>Deals</span><span>−{{ Money::fmt($o->deal_discount, true) }}</span></div>@endif
        @if($o->slab_discount > 0)<div class="kv"><span>Volume</span><span>−{{ Money::fmt($o->slab_discount, true) }}</span></div>@endif
        @if($o->coupon_discount > 0)<div class="kv"><span>Coupon {{ $o->coupon_code }}</span><span>−{{ Money::fmt($o->coupon_discount, true) }}</span></div>@endif
        <div class="kv"><span>Taxable</span><span>{{ Money::fmt($o->taxable_amount, true) }}</span></div><div class="kv muted"><span>CGST + SGST / IGST</span><span>{{ Money::fmt($o->cgst + $o->sgst + $o->igst, true) }}</span></div>
        <div class="kv total"><span>Total</span><span>{{ Money::fmt($o->total, true) }}</span></div>
        <div class="kv"><span>Payment</span><span>{{ strtoupper($o->payment_method) }} · {{ $o->payment_status }}</span></div>
        <div class="kv"><span>Delivery agent</span><span>{{ $o->agent?->name ?? '—' }}</span></div></div>
      @if($o->tickets->count())<div class="card"><h2 class="title-m mb-2">Support tickets</h2>@foreach($o->tickets as $t)<a class="list-item" href="{{ route('staff.support.show', $t) }}" wire:navigate>{{ $t->number }} · {{ $t->subject }}</a>@endforeach</div>@endif
    </div>
  </div>

  @if($confirmCancel)<div class="scrim"><div class="dialog"><h2 class="headline-s mb-3">Cancel {{ $o->number }}?</h2><div class="muted">Stock and credit are released; paid orders are marked refunded. The shopkeeper is notified with your note as the reason.</div>
    <div class="actions"><button class="btn btn-text" wire:click="$set('confirmCancel', false)">Back</button><button class="btn btn-danger" wire:click="move('cancelled')">Cancel order</button></div></div></div>@endif
</div>
