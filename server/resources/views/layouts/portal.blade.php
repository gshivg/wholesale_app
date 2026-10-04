@php
  $u = auth()->user();
  $nav = [
    ['portal', 'home', __('app.nav.home'), 'portal'],
    ['portal.catalog', 'storefront', __('app.nav.catalog'), 'portal/catalog*'],
    ['portal.offers', 'local_offer', __('app.nav.offers'), 'portal/offers'],
    ['portal.cart', 'shopping_cart', __('app.nav.cart'), 'portal/cart'],
    ['portal.orders', 'receipt_long', __('app.nav.orders'), 'portal/orders*'],
    ['portal.tickets', 'support_agent', __('app.nav.support'), 'portal/support*'],
    ['portal.profile', 'account_circle', __('app.nav.profile'), 'portal/profile'],
  ];
  $bottom = [$nav[0], $nav[1], $nav[4], $nav[5]];
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('layouts.partials-head')<title>{{ $title ?? config('shared.brand.name') }}</title></head>
<body>
<header class="app-bar">
  <a href="{{ route('portal') }}" class="brand" wire:navigate><span class="logo">R</span>{{ config('shared.brand.name') }}</a>
  <span class="spacer"></span>
  <livewire:portal.badges />
  <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="{{ __('app.auth.logout') }}"><span class="material-symbols-outlined">logout</span></button></form>
</header>
<div class="gold-line"></div>
<div class="shell">
  <aside class="drawer">
    <div class="section">{{ $u->shop?->shop_name ?? $u->name }}</div>
    @foreach($nav as [$route, $icon, $label, $pattern])
      <a class="nav-item {{ request()->is($pattern) ? 'active' : '' }}" href="{{ route($route) }}" wire:navigate><span class="material-symbols-outlined">{{ $icon }}</span>{{ $label }}</a>
    @endforeach
  </aside>
  <main class="has-bottom-nav">{{ $slot }}</main>
</div>
<nav class="bottom-nav">
  @foreach($bottom as [$route, $icon, $label, $pattern])
    <a class="{{ request()->is($pattern) ? 'active' : '' }}" href="{{ route($route) }}" wire:navigate><span class="pill"><span class="material-symbols-outlined">{{ $icon }}</span></span>{{ $label }}</a>
  @endforeach
</nav>
@include('layouts.partials-toast')
@livewireScripts
</body></html>
