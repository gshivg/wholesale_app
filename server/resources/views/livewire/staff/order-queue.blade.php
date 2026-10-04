@php use App\Support\Money; @endphp
<div class="page stack-lg" wire:poll.12s>
  <div class="row between wrap">
    <div><h1 class="headline-m">{{ $titles[0] }}</h1><div class="muted">{{ auth()->user()->hub?->name ?? 'All hubs' }} · live — refreshes automatically</div></div>
    @if($hubs->count())<x-m3.select label="Hub" wire:model.live="hub" style="min-width:240px"><option value="">All hubs</option>@foreach($hubs as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach</x-m3.select>@endif
  </div>

  <div class="grid cols-4">
    @foreach([['confirmed','To pack','pending_actions'],['packing','Packing now','inventory_2'],['packed','Ready to dispatch','package_2'],['out_for_delivery','On the road','local_shipping']] as [$s,$l,$ic])
      <div class="card stat-card stat"><div class="ic"><span class="material-symbols-outlined">{{ $ic }}</span></div><div class="v">{{ $counts[$s] ?? 0 }}</div><div class="l">{{ $l }}</div></div>
    @endforeach
  </div>

  <div class="tabs">@foreach($tabs as $k => $l)<button class="tab {{ $tab === $k ? 'active' : '' }}" wire:click="setTab('{{ $k }}')">{{ $l }}@if($k !== 'mine' && $k !== 'delivered') ({{ $counts[$k] ?? 0 }})@endif</button>@endforeach</div>

  <div class="grid auto-lg">
    @forelse($orders as $o)
      <div class="card stack" wire:key="q{{ $o->id }}" x-data="{ picked: {}, get all() { return {{ $o->items->count() }} === Object.values(this.picked).filter(Boolean).length } }">
        <div class="row between"><div><div class="title-m">{{ $o->number }}</div><div class="muted">{{ $o->shop->shop_name }} · {{ $o->shipping_address['city'] }}</div></div><x-m3.status :status="$o->status" /></div>
        <div class="row wrap" style="gap:8px"><span class="chip gold">{{ $o->total_cases }} cases</span>@if($o->items->sum('free_quantity'))<span class="chip gold">+{{ $o->items->sum('free_quantity') }} free</span>@endif<span class="chip">{{ $o->hub->code }}</span>@if($o->notes)<span class="chip" title="{{ $o->notes }}"><span class="material-symbols-outlined icon-sm">sticky_note_2</span>Note</span>@endif</div>
        @if($o->notes)<div class="banner warn"><span class="material-symbols-outlined">sticky_note_2</span><div>{{ $o->notes }}</div></div>@endif

        @if($mode === 'packaging')
          <div class="stack-sm">@foreach($o->items as $i)
            <label class="check" style="padding:6px 0;border-top:1px dashed var(--md-outline-variant)"><input type="checkbox" x-model="picked[{{ $i->id }}]" @disabled($o->status !== 'packing')><span class="grow">{{ $i->name }} <span class="muted">· {{ $i->label }}</span></span><b>× {{ $i->quantity }}@if($i->free_quantity) <span class="gold">+{{ $i->free_quantity }}</span>@endif</b></label>
          @endforeach</div>
          @if($o->status === 'confirmed')<button class="btn btn-filled" wire:click="move({{ $o->id }}, 'packing')"><span class="material-symbols-outlined">play_arrow</span>Start packing</button>
          @elseif($o->status === 'packing')<button class="btn btn-gold" wire:click="move({{ $o->id }}, 'packed')" :disabled="!all"><span class="material-symbols-outlined">package_2</span>Mark as packed</button><div class="muted label-m" x-show="!all">Tick every line to confirm the pick.</div>
          @else<div class="muted">Packed {{ $o->packed_at?->diffForHumans() }}</div>@endif

        @elseif($mode === 'warehouse')
          <div class="stack-sm">@foreach($o->items as $i)<div class="row between" style="border-top:1px dashed var(--md-outline-variant);padding:4px 0"><span>{{ $i->name }} <span class="muted">· {{ $i->label }}</span></span><b>× {{ $i->quantity + $i->free_quantity }}</b></div>@endforeach</div>
          @if($o->status === 'packed')
            <x-m3.select label="Delivery agent" wire:model="agent.{{ $o->id }}"><option value="">Auto-assign (least busy)</option>@foreach($agents->where('hub_id', $o->hub_id) as $a)<option value="{{ $a->id }}">{{ $a->name }} · {{ $a->load }} active</option>@endforeach</x-m3.select>
            <button class="btn btn-gold" wire:click="move({{ $o->id }}, 'dispatched')"><span class="material-symbols-outlined">outbound</span>Parcel left the warehouse</button>
          @else<div class="muted">Agent: {{ $o->agent?->name ?? '—' }} · left {{ $o->dispatched_at?->diffForHumans() }}</div>@endif

        @else
          <div class="card tonal stack-sm"><div class="title-s">{{ $o->shipping_address['name'] }}</div><div>{{ $o->shipping_address['line1'] }} {{ $o->shipping_address['line2'] }}, {{ $o->shipping_address['landmark'] }}<br>{{ $o->shipping_address['city'] }} {{ $o->shipping_address['pincode'] }}</div>
            <div class="row wrap"><a class="btn btn-tonal btn-sm" href="tel:{{ $o->shipping_address['phone'] }}"><span class="material-symbols-outlined">call</span>{{ $o->shipping_address['phone'] }}</a>
              <a class="btn btn-tonal btn-sm" target="_blank" href="https://www.google.com/maps/search/?api=1&query={{ urlencode($o->shipping_address['line1'].' '.$o->shipping_address['city'].' '.$o->shipping_address['pincode']) }}"><span class="material-symbols-outlined">map</span>Map</a></div></div>
          @if($o->payment_method === 'cod' && $o->payment_status !== 'paid')<div class="banner warn"><span class="material-symbols-outlined">payments</span><div><b>Collect cash: {{ Money::fmt($o->total, true) }}</b></div></div>@else<div class="muted">Payment: {{ $o->payment_method }} / {{ $o->payment_status }}</div>@endif
          @if($o->status === 'dispatched')<button class="btn btn-filled" wire:click="move({{ $o->id }}, 'out_for_delivery')"><span class="material-symbols-outlined">local_shipping</span>Start delivery</button>
          @elseif($o->status === 'out_for_delivery')
            <x-m3.input label="Delivery OTP from shopkeeper" inputmode="numeric" maxlength="4" wire:model="otp.{{ $o->id }}" />
            <button class="btn btn-gold" wire:click="move({{ $o->id }}, 'delivered')"><span class="material-symbols-outlined">task_alt</span>Mark delivered</button>
          @else<div class="success">Delivered {{ $o->delivered_at?->format('h:i A') }}</div>@endif
        @endif
        <a class="btn btn-text btn-sm" style="align-self:flex-start" href="{{ auth()->user()->hasRole(\App\Enums\Role::SuperAdmin, \App\Enums\Role::OrderManager) ? route('staff.orders.show', $o) : '#' }}">{{ $o->items_count }} line(s) · {{ Money::fmt($o->total, true) }}</a>
      </div>
    @empty
      <div class="card empty" style="grid-column:1/-1"><span class="material-symbols-outlined">{{ $titles[1] }}</span><div class="title-m">Nothing here right now</div><div>New work appears automatically.</div></div>
    @endforelse
  </div>
</div>
