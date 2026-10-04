@php use App\Support\Money; @endphp
<div class="page stack-lg">
  <div class="row between wrap"><div><a class="btn btn-text" href="{{ route('staff.shops') }}" wire:navigate><span class="material-symbols-outlined">arrow_back</span>Shopkeepers</a><h1 class="headline-m">{{ $s->shop_name }}</h1><div class="muted">{{ $s->shop_type }} · registered {{ $s->created_at->format('d M Y') }}</div></div>
    <span class="badge {{ ['approved' => 'success', 'pending' => 'gold', 'rejected' => 'error', 'suspended' => 'error'][$s->status] }}">{{ $s->status }}</span></div>

  <div class="grid cols-2" style="align-items:start">
    <div class="card stack-sm"><h2 class="title-m mb-2">Business details</h2>
      @if($s->photo_url)<img src="{{ $s->photo_url }}" alt="" style="width:100%;max-height:220px;object-fit:cover;border-radius:12px">@endif
      <div class="kv"><span class="muted">Owner</span><span>{{ $s->user->name }}</span></div>
      <div class="kv"><span class="muted">Mobile</span><span>{{ $s->user->phone }} @if($s->user->phone_verified_at)<span class="badge success">verified</span>@else<span class="badge error">unverified</span>@endif</span></div>
      <div class="kv"><span class="muted">Email</span><span>{{ $s->user->email }}</span></div>
      <div class="kv"><span class="muted">PAN</span><span>{{ $s->pan }}</span></div>
      <div class="kv"><span class="muted">GSTIN</span><span>{{ $s->gstin ?: 'unregistered' }}</span></div>
      <div class="divider"></div>
      <div>{{ $s->address_line1 }} {{ $s->address_line2 }}</div><div class="muted">{{ $s->landmark }} {{ $s->city }}, {{ $s->district }} {{ $s->pincode }}</div>
      <div class="kv"><span class="muted">Serving hub</span><span>{{ $s->hub?->name ?? 'none (unserviceable PIN)' }}</span></div>
      @if($s->status_note)<div class="banner warn"><span class="material-symbols-outlined">notes</span><div>{{ $s->status_note }}</div></div>@endif
    </div>
    <div class="stack">
      <div class="card stack">
        <h2 class="title-m">Decision</h2>
        <x-m3.input label="Credit limit (₹) given on approval" type="number" wire:model="creditLimit" />
        <x-m3.input label="Note / reason (required to reject or suspend)" wire:model="note" />
        @error('note')<div class="error label-m">{{ $message }}</div>@enderror
        <div class="row wrap">
          @if($s->status !== 'approved')<button class="btn btn-gold" wire:click="approve"><span class="material-symbols-outlined">verified</span>{{ $s->status === 'pending' ? 'Approve' : 'Re-approve' }}</button>@endif
          @if($s->status === 'pending')<button class="btn btn-danger-text" wire:click="reject">Reject</button>@endif
          @if($s->status === 'approved')<button class="btn btn-danger-text" wire:click="suspend">Suspend</button>@endif
        </div>
      </div>
      <div class="card stack-sm"><h2 class="title-m mb-2">Credit</h2>
        <div class="kv"><span>Limit</span><b>{{ Money::fmt($s->credit_limit, true) }}</b></div><div class="kv"><span>Used</span><b>{{ Money::fmt($s->credit_used, true) }}</b></div><div class="kv"><span>Available</span><b>{{ Money::fmt($s->credit_available, true) }}</b></div>
        <div class="row mt-2"><button class="btn btn-tonal btn-sm" wire:click="saveCredit">Save limit</button></div>
        <form class="row mt-2" wire:submit="recordRepayment"><x-m3.input class="grow" label="Repayment received (₹)" type="number" wire:model="repayment" /><button class="btn btn-filled btn-sm">Record</button></form>
        @error('repayment')<div class="error label-m">{{ $message }}</div>@enderror</div>
      <div class="grid cols-2"><div class="card stat"><div class="v">{{ $orderCount }}</div><div class="l">Orders</div></div><div class="card stat"><div class="v">{{ Money::fmt($lifetime, true) }}</div><div class="l">Lifetime value</div></div></div>
    </div>
  </div>
  <div class="card flat"><div class="card-header" style="padding:16px 16px 0"><h2 class="title-m">Recent orders</h2></div>
    @forelse($orders as $o)<a class="list-item" href="{{ route('staff.orders.show', $o) }}" wire:navigate style="color:inherit;text-decoration:none"><div class="grow"><b>{{ $o->number }}</b> <span class="muted">· {{ $o->created_at->format('d M Y') }}</span></div><x-m3.money :v="$o->total" /><x-m3.status :status="$o->status" /></a>@empty<div class="empty">No orders yet.</div>@endforelse</div>
</div>
