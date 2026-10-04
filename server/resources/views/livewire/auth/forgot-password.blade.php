<div class="auth-wrap">
  <div class="auth-card stack-lg">
    <div><h1 class="headline-s">Reset your password</h1><div class="muted">We'll send a one-time code to your registered mobile number.</div></div>
    @if($step === 1)
      <form class="stack" wire:submit="send">
        <x-m3.input label="Mobile number" type="tel" wire:model="phone" />
        <button class="btn btn-filled btn-lg">Send OTP</button>
      </form>
    @else
      <form class="stack" wire:submit="reset_password">
        @if($hint)<div class="banner warn"><span class="material-symbols-outlined">sms</span><div>Prototype mode: your OTP is <b>{{ $hint }}</b></div></div>@endif
        <x-m3.input label="OTP" wire:model="code" inputmode="numeric" maxlength="6" />
        <x-m3.input label="New password" type="password" wire:model="password" />
        <x-m3.input label="Confirm password" type="password" wire:model="password_confirmation" />
        <button class="btn btn-filled btn-lg">Update password</button>
      </form>
    @endif
    <a class="btn btn-text" href="{{ route('login') }}" wire:navigate>Back to login</a>
  </div>
</div>
