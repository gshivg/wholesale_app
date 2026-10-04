<div class="auth-wrap">
  <div class="auth-card wide stack-lg">
    <div>
      <h1 class="headline-s">Register your shop</h1>
      <div class="muted">Wholesale prices, bulk ordering and doorstep delivery. Approval usually takes under a day.</div>
    </div>
    <div class="stepper">@foreach([1,2,3,4] as $s)<span class="s {{ $step >= $s ? 'on' : '' }}"></span>@endforeach</div>

    @if($step === 1)
      <form class="stack" wire:submit="next">
        <h2 class="title-m">1 · About you</h2>
        <div class="grid cols-2">
          <x-m3.input label="Owner's full name" wire:model="name" autocomplete="name" />
          <x-m3.input label="Mobile number" type="tel" inputmode="numeric" maxlength="10" wire:model="phone" hint="10 digits, used to log in" />
        </div>
        <x-m3.input label="Email address" type="email" wire:model="email" autocomplete="email" />
        <div class="grid cols-2">
          <x-m3.input label="Password" type="password" wire:model="password" hint="At least 8 characters" />
          <x-m3.input label="Confirm password" type="password" wire:model="password_confirmation" />
        </div>
        <div class="row between"><a class="btn btn-text" href="{{ route('login') }}" wire:navigate>I already have an account</a><button class="btn btn-filled">Next <span class="material-symbols-outlined">arrow_forward</span></button></div>
      </form>
    @elseif($step === 2)
      <form class="stack" wire:submit="next">
        <h2 class="title-m">2 · Your shop</h2>
        <x-m3.input label="Shop name" wire:model="shop_name" />
        <x-m3.select label="Type of shop" wire:model="shop_type">@foreach($types as $t)<option>{{ $t }}</option>@endforeach</x-m3.select>
        <div class="grid cols-2">
          <x-m3.input label="PAN" wire:model="pan" maxlength="10" style="text-transform:uppercase" hint="e.g. ABCDE1234F" />
          <x-m3.input label="GSTIN (optional)" wire:model="gstin" maxlength="15" style="text-transform:uppercase" hint="Leave blank if unregistered" />
        </div>
        <div class="row between"><button type="button" class="btn btn-text" wire:click="back">Back</button><button class="btn btn-filled">Next <span class="material-symbols-outlined">arrow_forward</span></button></div>
      </form>
    @elseif($step === 3)
      <form class="stack" wire:submit="submit">
        <h2 class="title-m">3 · Delivery address</h2>
        <x-m3.input label="Address line 1" wire:model="address_line1" />
        <div class="grid cols-2">
          <x-m3.input label="Address line 2 (optional)" wire:model="address_line2" />
          <x-m3.input label="Landmark (optional)" wire:model="landmark" />
        </div>
        <div class="grid cols-2">
          <x-m3.input label="PIN code" type="text" inputmode="numeric" maxlength="6" wire:model.live.debounce.300ms="pincode" />
          <x-m3.input label="City / town" wire:model="city" />
        </div>
        @if(strlen($pincode) === 6)
          @if($this->area)<div class="banner success"><span class="material-symbols-outlined">check_circle</span><div>Great — we deliver to <b>{{ $this->area['district'] }}</b> district.</div></div>
          @else<div class="banner warn"><span class="material-symbols-outlined">location_off</span><div>We currently serve {{ implode(', ', $districts) }} only. Coming to more districts soon.</div></div>@endif
        @endif
        <div>
          <div class="label-l mb-2">Shop photo (optional)</div>
          <input type="file" wire:model="shop_photo" accept="image/*">
          @error('shop_photo')<div class="err error">{{ $message }}</div>@enderror
          @if($shop_photo)<img src="{{ $shop_photo->temporaryUrl() }}" alt="" style="height:96px;border-radius:12px;margin-top:8px">@endif
        </div>
        <div class="row between"><button type="button" class="btn btn-text" wire:click="back">Back</button><button class="btn btn-gold" wire:loading.attr="disabled">Create account</button></div>
      </form>
    @else
      <form class="stack" wire:submit="verify">
        <h2 class="title-m">4 · Verify your mobile</h2>
        <div class="muted">Enter the {{ config('shared.otp.length') }}-digit code sent to <b>{{ $verifyPhone }}</b>.</div>
        @if($otpHint)<div class="banner warn"><span class="material-symbols-outlined">sms</span><div>Prototype mode — no SMS is sent. Use OTP <b>{{ $otpHint }}</b>.</div></div>@endif
        <x-m3.input label="OTP" inputmode="numeric" maxlength="6" wire:model="otp" />
        <div class="row between"><button type="button" class="btn btn-text" wire:click="resend">Resend OTP</button><button class="btn btn-filled">Verify & continue</button></div>
      </form>
    @endif
  </div>
</div>
