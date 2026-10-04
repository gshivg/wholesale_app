@php use App\Support\Money; @endphp
<div class="page stack-lg">
  <div class="row between wrap"><div><h1 class="headline-m">Offers & coupons</h1><div class="muted">Volume slabs and product deals apply automatically. Coupons are redeemed with a code — share private ones with customers directly.</div></div>
    <button class="btn btn-gold" wire:click="open('{{ ['coupons' => 'coupon', 'deals' => 'deal', 'slabs' => 'slab'][$tab] }}')"><span class="material-symbols-outlined">add</span>New {{ ['coupons' => 'coupon', 'deals' => 'deal', 'slabs' => 'slab'][$tab] }}</button></div>
  <div class="tabs">@foreach(['coupons' => 'Coupons', 'deals' => 'Product deals', 'slabs' => 'Volume slabs'] as $k => $l)<button class="tab {{ $tab === $k ? 'active' : '' }}" wire:click="$set('tab','{{ $k }}')">{{ $l }}</button>@endforeach</div>

  @if($tab === 'coupons')
    <div class="grid auto-lg">@foreach($coupons as $c)
      <div class="card stack-sm" wire:key="c{{ $c->id }}" style="{{ $c->is_active ? '' : 'opacity:.55' }}"><div class="row between"><span class="title-l" style="letter-spacing:2px">{{ $c->code }}</span><span class="badge {{ $c->is_public ? 'primary' : 'gold' }}">{{ $c->is_public ? 'public' : 'private' }}</span></div>
        <div class="title-s">{{ $c->title }} · {{ $c->summary() }}</div><div class="muted">Min order {{ Money::fmt($c->min_order, true) }} · {{ $c->per_user_limit }}/user @if($c->first_order_only)· first order @endif</div>
        <div class="muted">Used {{ $c->times_used }}@if($c->usage_limit) / {{ $c->usage_limit }}@endif · @if($c->ends_at)ends {{ $c->ends_at->format('d M Y') }}@else no expiry @endif</div>
        <div class="row"><button class="btn btn-tonal btn-sm" wire:click="open('coupon', {{ $c->id }})">Edit</button><button class="btn btn-text btn-sm" wire:click="toggle('coupon', {{ $c->id }})">{{ $c->is_active ? 'Disable' : 'Enable' }}</button><button class="btn btn-danger-text btn-sm" wire:click="delete('coupon', {{ $c->id }})" wire:confirm="Delete this coupon? (If it was used, it is disabled instead.)">Delete</button></div></div>
    @endforeach</div>
  @elseif($tab === 'deals')
    <div class="grid auto-lg">@foreach($deals as $d)
      <div class="card stack-sm" wire:key="d{{ $d->id }}" style="{{ $d->is_active ? '' : 'opacity:.55' }}"><div class="row between"><span class="title-m">{{ $d->title }}</span><span class="badge gold">{{ $d->summary() }}</span></div>
        <div class="muted">Applies to: {{ $d->product?->name ?? ($d->category ? 'All ' . $d->category->name : 'All products') }} · min {{ $d->min_qty }} case(s)</div><div class="muted">{{ $d->starts_at?->format('d M') ?? 'now' }} → {{ $d->ends_at?->format('d M Y') ?? 'open' }}</div>
        <div class="row"><button class="btn btn-tonal btn-sm" wire:click="open('deal', {{ $d->id }})">Edit</button><button class="btn btn-text btn-sm" wire:click="toggle('deal', {{ $d->id }})">{{ $d->is_active ? 'Disable' : 'Enable' }}</button><button class="btn btn-danger-text btn-sm" wire:click="delete('deal', {{ $d->id }})" wire:confirm="Delete deal?">Delete</button></div></div>
    @endforeach</div>
  @else
    <div class="grid cols-3">@foreach($slabs as $s)
      <div class="card tonal stack-sm center" wire:key="s{{ $s->id }}" style="{{ $s->is_active ? '' : 'opacity:.55' }}"><div class="display" style="font-size:40px">{{ rtrim(rtrim($s->percent, '0'), '.') }}%</div><div>on orders above {{ Money::fmt($s->min_subtotal, true) }}</div>
        <div class="row" style="justify-content:center"><button class="btn btn-tonal btn-sm" wire:click="open('slab', {{ $s->id }})">Edit</button><button class="btn btn-text btn-sm" wire:click="toggle('slab', {{ $s->id }})">{{ $s->is_active ? 'Disable' : 'Enable' }}</button><button class="btn btn-danger-text btn-sm" wire:click="delete('slab', {{ $s->id }})">Delete</button></div></div>
    @endforeach</div>
  @endif

  @if($form === 'coupon')<div class="scrim"><form class="dialog stack" wire:submit="saveCoupon"><h2 class="headline-s">{{ $editId ? 'Edit' : 'New' }} coupon</h2>
    <div class="grid cols-2"><x-m3.input label="Code" wire:model="code" style="text-transform:uppercase" /><x-m3.input label="Title" wire:model="title" /></div><x-m3.input label="Description" wire:model="description" />
    <div class="grid cols-3"><x-m3.select label="Type" wire:model.live="ctype"><option value="percent">Percent off</option><option value="flat">Flat ₹ off</option></x-m3.select><x-m3.input label="{{ $ctype === 'percent' ? 'Percent' : 'Amount ₹' }}" type="number" wire:model="value" /><x-m3.input label="Max discount ₹" type="number" wire:model="max_discount" /></div>
    <div class="grid cols-3"><x-m3.input label="Min order ₹" type="number" wire:model="min_order" /><x-m3.input label="Total usage limit" type="number" wire:model="usage_limit" /><x-m3.input label="Uses per shop" type="number" wire:model="per_user_limit" /></div>
    <div class="grid cols-2"><x-m3.input label="Starts" type="date" wire:model="starts_at" /><x-m3.input label="Ends" type="date" wire:model="ends_at" /></div>
    <div class="row wrap"><label class="check"><input type="checkbox" wire:model="is_public"> Listed publicly in app</label><label class="check"><input type="checkbox" wire:model="first_order_only"> First order only</label><label class="check"><input type="checkbox" wire:model="is_active"> Active</label></div>
    <div class="actions"><button type="button" class="btn btn-text" wire:click="$set('form', null)">Cancel</button><button class="btn btn-filled">Save</button></div></form></div>@endif

  @if($form === 'deal')<div class="scrim"><form class="dialog stack" wire:submit="saveDeal"><h2 class="headline-s">{{ $editId ? 'Edit' : 'New' }} deal</h2>
    <x-m3.input label="Title" wire:model="title" /><x-m3.input label="Description" wire:model="description" />
    <div class="grid cols-2"><x-m3.select label="Deal type" wire:model.live="dtype"><option value="percent">Percent off</option><option value="flat">Flat ₹ off per case</option><option value="bxgy">Buy X get Y free (cases)</option></x-m3.select><x-m3.input label="Minimum cases" type="number" wire:model="dmin_qty" /></div>
    @if($dtype === 'bxgy')<div class="grid cols-2"><x-m3.input label="Buy (cases)" type="number" wire:model="buy_qty" /><x-m3.input label="Get free (cases)" type="number" wire:model="free_qty" /></div>@else<x-m3.input label="{{ $dtype === 'percent' ? 'Percent' : 'Amount ₹ per case' }}" type="number" wire:model="dvalue" />@endif
    <div class="grid cols-2"><x-m3.select label="Applies to" wire:model.live="target"><option value="all">All products</option><option value="category">A category</option><option value="product">A product</option></x-m3.select>
      @if($target === 'category')<x-m3.select label="Category" wire:model="target_id"><option value="">Select…</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</x-m3.select>@elseif($target === 'product')<x-m3.select label="Product" wire:model="target_id"><option value="">Select…</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</x-m3.select>@endif</div>
    <div class="grid cols-2"><x-m3.input label="Starts" type="date" wire:model="starts_at" /><x-m3.input label="Ends" type="date" wire:model="ends_at" /></div><label class="check"><input type="checkbox" wire:model="is_active"> Active</label>
    <div class="actions"><button type="button" class="btn btn-text" wire:click="$set('form', null)">Cancel</button><button class="btn btn-filled">Save</button></div></form></div>@endif

  @if($form === 'slab')<div class="scrim"><form class="dialog stack" wire:submit="saveSlab"><h2 class="headline-s">{{ $editId ? 'Edit' : 'New' }} volume slab</h2>
    <x-m3.input label="Order subtotal above ₹ (after deals, before GST)" type="number" wire:model="min_subtotal" /><x-m3.input label="Discount %" type="number" step="0.1" wire:model="percent" /><label class="check"><input type="checkbox" wire:model="is_active"> Active</label>
    <div class="actions"><button type="button" class="btn btn-text" wire:click="$set('form', null)">Cancel</button><button class="btn btn-filled">Save</button></div></form></div>@endif
</div>
