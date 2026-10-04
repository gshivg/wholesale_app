<div class="page stack-lg">
  <div class="row between wrap">
    <div><h1 class="headline-m">Shop wholesale</h1><div class="muted">Prices are per case, excluding GST. Stock shown for your hub.</div></div>
    <div class="field" style="min-width:280px"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Search products or brands</label></div>
  </div>
  @unless($canOrder)<div class="banner warn"><span class="material-symbols-outlined">hourglass_top</span><div>Browsing only — you can order once your account is approved.</div></div>@endunless
  <div class="row wrap">
    <button class="chip {{ $category === '' ? 'selected' : '' }}" wire:click="setCategory('')">All</button>
    @foreach($categories as $c)<button class="chip {{ $category === (string) $c->id ? 'selected' : '' }}" wire:click="setCategory('{{ $c->id }}')"><span class="material-symbols-outlined icon-sm">{{ $c->icon ?? 'category' }}</span>{{ $c->name }}</button>@endforeach
  </div>

  <div class="grid auto-lg" wire:loading.class="opacity-50">
    @forelse($products as $p)
      @php $pd = $deals->filter(fn ($d) => $p->variants->contains(fn ($v) => $d->product_variant_id ? $d->product_variant_id === $v->id : ($d->product_id ? $d->product_id === $p->id : ($d->category_id ? $d->category_id === $p->category_id : true)))); @endphp
      <div class="card product" wire:key="p{{ $p->id }}">
        <div class="thumb" style="background:linear-gradient(135deg,{{ $p->accent ?? '#1B3A8C' }},{{ $p->accent ?? '#1B3A8C' }}cc 60%,#0F2461)">
          @if($p->image_url)<img src="{{ $p->image_url }}" alt="">@else<span class="material-symbols-outlined">{{ $p->category->icon ?? 'cookie' }}</span>@endif
          @if($pd->count())<span class="badge gold deal"><span class="material-symbols-outlined icon-sm">sell</span>{{ $pd->first()->summary() }}</span>@endif
        </div>
        <div class="body">
          <div><div class="title-m">{{ $p->name }}</div><div class="muted">{{ $p->brand }} · GST {{ rtrim(rtrim($p->gst_rate, '0'), '.') }}%</div></div>
          @foreach($p->variants as $v)
            @php $avail = $availability($v); $q = $qty[$v->id] ?? $v->min_qty; @endphp
            <div class="variant-row" wire:key="v{{ $v->id }}">
              <div>
                <div class="title-s">{{ $v->label }}</div>
                <div><span class="price"><x-m3.money :v="$v->price" /></span> <span class="mrp"><x-m3.money :v="$v->mrp" /></span> <span class="muted">/ case</span></div>
                <div class="muted">Min {{ $v->min_qty }} · @if($avail >= $v->min_qty)<span class="success">{{ $avail }} in stock</span>@else<span class="error">Out of stock</span>@endif @if(isset($inCart[$v->id]))· <b>{{ $inCart[$v->id] }} in cart</b>@endif</div>
              </div>
              <div class="stack-sm" style="align-items:flex-end">
                <div class="qty"><button type="button" wire:click="step({{ $v->id }}, -1, {{ $v->min_qty }})">−</button><input type="number" min="{{ $v->min_qty }}" value="{{ $q }}" wire:change="$set('qty.{{ $v->id }}', $event.target.value)"><button type="button" wire:click="step({{ $v->id }}, 1, {{ $v->min_qty }})">+</button></div>
                <button class="btn btn-filled btn-sm" wire:click="add({{ $v->id }})" @disabled(! $canOrder || $avail < $v->min_qty)><span class="material-symbols-outlined">add_shopping_cart</span>Add</button>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    @empty
      <div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">search_off</span><div>No products match your search.</div></div>
    @endforelse
  </div>
  <x-m3.pager :p="$products" />
</div>
