<div class="auth-wrap">
  <form class="auth-card stack-lg" wire:submit="login">
    <div class="center">
      <span class="logo" style="display:inline-grid;place-items:center;width:56px;height:56px;border-radius:16px;background:var(--md-primary);color:var(--md-tertiary);font-size:28px;font-weight:700">R</span>
      <h1 class="headline-s mt-2">Welcome back</h1>
      <div class="muted">Log in to order, track deliveries and get support.</div>
    </div>
    <div class="stack">
      <x-m3.input label="Mobile number" type="tel" inputmode="numeric" wire:model="phone" autocomplete="username" />
      <x-m3.input label="Password" type="password" wire:model="password" autocomplete="current-password" />
      <label class="check"><input type="checkbox" wire:model="remember"> Keep me signed in</label>
    </div>
    <div class="stack">
      <button class="btn btn-filled btn-lg btn-block" wire:loading.attr="disabled">Log in</button>
      <a class="btn btn-text" href="{{ route('password.forgot') }}" wire:navigate>Forgot password?</a>
      <div class="divider"></div>
      <div class="center muted">New shopkeeper?</div>
      <a class="btn btn-outlined btn-block" href="{{ route('register') }}" wire:navigate>Register your shop</a>
    </div>
    <div class="banner info"><span class="material-symbols-outlined">info</span><div>
      <b>Prototype demo logins</b> (password <code>password</code>):<br>
      Shopkeeper 9100000001 · Admin 9000000001 · Orders 9000000002 · Support 9000000003 · Packing 9000000010 · Warehouse 9000000011 · Driver 9000000012
    </div></div>
  </form>
</div>
