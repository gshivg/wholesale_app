<div class="page stack-lg">
  <div><h1 class="headline-m">Offers & deals</h1><div class="muted">Deals apply automatically in your cart. Coupons are redeemed with a code — public ones are listed here, others are shared by our sales team.</div></div>

  <section class="stack"><h2 class="title-l">Volume discounts</h2>
    <div class="grid cols-3">@foreach($slabs as $s)<div class="card tonal center"><div class="display" style="font-size:40px">{{ rtrim(rtrim($s->percent, '0'), '.') }}%</div><div>off on orders above <b><x-m3.money :v="$s->min_subtotal" /></b></div></div>@endforeach</div></section>

  <section class="stack"><h2 class="title-l">Redeem a coupon</h2>
    <div class="card"><form class="row" wire:submit="redeem()"><x-m3.input class="grow" label="Coupon code" wire:model="code" style="text-transform:uppercase" /><button class="btn btn-gold">Redeem</button></form>
      @error('code')<div class="error label-m mt-2">{{ $message }}</div>@enderror
      @if($saved)<div class="banner success mt-3"><span class="material-symbols-outlined">check_circle</span><div><b>{{ $saved }}</b> is saved to your cart.</div></div>@endif</div>
    <div class="grid auto-lg">@foreach($coupons as $c)
      <div class="card gold stack-sm" style="border:2px dashed #B8860B"><div class="row between"><span class="title-l" style="letter-spacing:2px">{{ $c->code }}</span><span class="badge primary">{{ $c->summary() }}</span></div>
        <div class="title-s">{{ $c->title }}</div><div>{{ $c->description }}</div><div class="muted">Valid till {{ $c->ends_at?->format('d M Y') ?? 'further notice' }}</div>
        <button class="btn btn-filled btn-sm" style="align-self:flex-start" wire:click="redeem('{{ $c->code }}')">Save to cart</button></div>
    @endforeach</div></section>

  <section class="stack"><h2 class="title-l">Product deals</h2>
    <div class="grid auto-lg">@forelse($deals as $d)
      <div class="card stack-sm"><div class="row"><span class="badge gold">{{ strtoupper($d->type === 'bxgy' ? 'free cases' : $d->type) }}</span><span class="title-m">{{ $d->title }}</span></div>
        <div>{{ $d->description }}</div><div class="muted">{{ $d->summary() }} · min {{ $d->min_qty }} case(s) @if($d->ends_at)· ends {{ $d->ends_at->format('d M') }}@endif</div>
        <a class="btn btn-tonal btn-sm" style="align-self:flex-start" href="{{ route('portal.catalog', ['q' => $d->product?->name]) }}" wire:navigate>Shop now</a></div>
    @empty<div class="muted">No product deals right now.</div>@endforelse</div></section>
</div>
