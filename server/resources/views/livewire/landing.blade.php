<div>
  <section class="hero">
    <span class="material-symbols-outlined hero-art">cookie</span>
    <div class="container" style="position:relative">
      <span class="chip gold mb-3"><span class="material-symbols-outlined icon-sm">workspace_premium</span> Trusted by kirana & retail stores across UP</span>
      <h1 class="display">Stock your shelves<br><span class="accent">straight from the maker.</span></h1>
      <p class="body-l mt-3">{{ config('shared.brand.name') }} supplies chips, namkeen, biscuits, noodles and more in bulk — at factory-direct wholesale prices, with GST invoices and doorstep delivery from our Prayagraj warehouse and regional hubs.</p>
      <div class="row wrap mt-4">
        <a class="btn btn-gold btn-lg" href="{{ route('register') }}" wire:navigate>Register your shop</a>
        <a class="btn btn-outlined btn-lg" style="color:#fff;box-shadow:inset 0 0 0 1px rgb(255 255 255 / .6)" href="{{ route('login') }}" wire:navigate>Shopkeeper login</a>
      </div>
      <div class="row wrap mt-4" style="gap:32px;color:#F4D675">
        <div><div class="headline-s">{{ $productCount }}+</div><div class="label-m">PRODUCTS</div></div>
        <div><div class="headline-s">4</div><div class="label-m">DISTRICT HUBS</div></div>
        <div><div class="headline-s">Up to 6%</div><div class="label-m">VOLUME DISCOUNTS</div></div>
      </div>
    </div>
  </section>

  <section class="container" style="margin-top:-48px;position:relative">
    <div class="grid cols-4">
      @foreach([['percent','Wholesale pricing','Case-level prices with volume slabs, deals and shareable coupons.'],['receipt_long','GST-ready invoices','Proper tax invoices with HSN codes, CGST/SGST split.'],['local_shipping','Live delivery tracking','Follow every parcel from packing to your doorstep.'],['support_agent','Real human support','Raise a ticket in seconds; our agents reply fast.']] as [$ic,$t,$d])
        <div class="card feature"><div class="ic"><span class="material-symbols-outlined icon-lg">{{ $ic }}</span></div><div class="title-m">{{ $t }}</div><div class="muted mt-1">{{ $d }}</div></div>
      @endforeach
    </div>
  </section>

  <section id="products" class="container" style="margin-top:64px">
    <div class="row between wrap mb-3"><div><h2 class="headline-m">Our range</h2><div class="muted">Log in to see wholesale prices and stock at your nearest hub.</div></div><a class="btn btn-tonal" href="{{ route('login') }}" wire:navigate>View price list</a></div>
    <div class="row wrap mb-3">@foreach($categories as $c)<span class="chip selected"><span class="material-symbols-outlined icon-sm">{{ $c->icon ?? 'category' }}</span>{{ $c->name }} · {{ $c->products_count }}</span>@endforeach</div>
    <div class="grid auto">
      @foreach($featured as $p)
        <div class="card product">
          <div class="thumb" style="background:linear-gradient(135deg,{{ $p->accent ?? '#1B3A8C' }},{{ $p->accent ?? '#1B3A8C' }}cc 60%,#0F2461)"><span class="material-symbols-outlined">{{ $p->category->icon ?? 'cookie' }}</span><span class="badge gold deal">Bestseller</span></div>
          <div class="body"><div class="title-m">{{ $p->name }}</div><div class="muted">{{ $p->brand }} · {{ $p->category->name }}</div><div class="muted">{{ $p->description }}</div></div>
        </div>
      @endforeach
    </div>
  </section>

  <section id="how" class="container" style="margin-top:64px">
    <h2 class="headline-m mb-3">How it works</h2>
    <div class="grid cols-4">
      @foreach([['1','Register','Share your shop, PAN and GSTIN. Verify your mobile.'],['2','Get approved','Our team verifies your details — usually within a day.'],['3','Order in bulk','Pick cases, apply deals & coupons, pay online, on credit, or on delivery.'],['4','Track & receive','Follow packing, dispatch and delivery live. Give the OTP to our driver.']] as [$n,$t,$d])
        <div class="card outlined"><span class="badge gold mb-2">Step {{ $n }}</span><div class="title-m">{{ $t }}</div><div class="muted mt-1">{{ $d }}</div></div>
      @endforeach
    </div>
  </section>

  <section id="areas" class="container" style="margin-top:64px">
    <div class="card tonal">
      <div class="row between wrap"><div><h2 class="headline-s">Where we deliver</h2><div>From our Prayagraj main warehouse and distribution centres:</div></div>
      <div class="row wrap">@foreach(config('shared.serviceAreas') as $a)<span class="chip gold"><span class="material-symbols-outlined icon-sm">location_on</span>{{ $a['district'] }}</span>@endforeach</div></div>
    </div>
  </section>

  <section class="container" style="margin-top:32px">
    <div class="card gold row between wrap" style="padding:32px">
      <div><h2 class="headline-s">Order on the go with our app</h2><div>Android & iOS — same account, same cart as the website.</div></div>
      <div class="row"><span class="btn btn-filled"><span class="material-symbols-outlined">android</span> Get it on Google Play</span><span class="btn btn-outlined"><span class="material-symbols-outlined">phone_iphone</span> App Store</span></div>
    </div>
  </section>

  <footer id="contact" class="footer"><div class="container row between wrap">
    <div><div class="title-m" style="color:#fff">{{ config('shared.brand.name') }}</div><div>{{ config('shared.company.legalName') }} · {{ config('shared.company.address') }}</div><div>GSTIN {{ config('shared.company.gstin') }}</div></div>
    <div><div>Support: <a href="mailto:{{ config('shared.brand.supportEmail') }}">{{ config('shared.brand.supportEmail') }}</a></div><div>{{ config('shared.brand.supportPhone') }}</div></div>
  </div></footer>
</div>
