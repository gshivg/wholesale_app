@php use App\Support\Money; @endphp
<div class="page stack-lg">
  <h1 class="headline-m">Profile</h1>
  <div class="grid cols-2" style="align-items:start">
    <div class="card stack">
      <div class="row"><div class="lead" style="width:56px;height:56px;border-radius:50%;background:var(--md-primary);color:var(--md-tertiary);display:grid;place-items:center;font-size:24px;font-weight:700">{{ strtoupper(substr($shop->shop_name, 0, 1)) }}</div>
        <div><div class="title-l">{{ $shop->shop_name }}</div><span class="badge {{ ['approved' => 'success', 'pending' => 'gold', 'rejected' => 'error', 'suspended' => 'error'][$shop->status] }}">{{ $shop->status }}</span></div></div>
      <div class="divider"></div>
      <div class="kv"><span class="muted">Mobile</span><span>{{ auth()->user()->phone }}</span></div>
      <div class="kv"><span class="muted">Shop type</span><span>{{ $shop->shop_type }}</span></div>
      <div class="kv"><span class="muted">PAN</span><span>{{ $shop->pan }}</span></div>
      <div class="kv"><span class="muted">GSTIN</span><span>{{ $shop->gstin ?: '—' }}</span></div>
      <div class="kv"><span class="muted">Serving hub</span><span>{{ $shop->hub?->name ?? '—' }}</span></div>
      <div class="kv"><span class="muted">Credit limit</span><span>{{ Money::fmt($shop->credit_limit, true) }}</span></div>
      <div class="kv"><span class="muted">Credit used</span><span>{{ Money::fmt($shop->credit_used, true) }}</span></div>
      <div class="muted label-m">To change PAN/GSTIN or shop name, raise a support ticket (Account / KYC).</div>
    </div>
    <form class="card stack" wire:submit="save">
      <h2 class="title-m">Contact & address</h2>
      <x-m3.input label="Your name" wire:model="name" />
      <x-m3.input label="Email" type="email" wire:model="email" />
      <x-m3.input label="Address line 1" wire:model="address_line1" />
      <div class="grid cols-2"><x-m3.input label="Address line 2" wire:model="address_line2" /><x-m3.input label="Landmark" wire:model="landmark" /></div>
      <div class="grid cols-2"><x-m3.input label="PIN code" wire:model="pincode" maxlength="6" /><x-m3.input label="City" wire:model="city" /></div>
      <x-m3.select label="Language" wire:model="locale">@foreach($locales as $l)<option value="{{ $l['code'] }}">{{ $l['name'] }}</option>@endforeach</x-m3.select>
      <button class="btn btn-filled" style="align-self:flex-end">Save changes</button>
    </form>
  </div>
  <form class="card stack" style="max-width:520px" wire:submit="changePassword">
    <h2 class="title-m">Change password</h2>
    <x-m3.input label="Current password" type="password" wire:model="current_password" />
    <x-m3.input label="New password" type="password" wire:model="password" />
    <x-m3.input label="Confirm new password" type="password" wire:model="password_confirmation" />
    <button class="btn btn-tonal" style="align-self:flex-end">Update password</button>
  </form>
</div>
