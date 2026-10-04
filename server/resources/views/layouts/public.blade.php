<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('layouts.partials-head')<title>{{ $title ?? config('shared.brand.name') . ' — ' . config('shared.brand.tagline') }}</title></head>
<body>
<header class="app-bar">
  <a href="/" class="brand" wire:navigate><span class="logo">R</span>{{ config('shared.brand.name') }}</a>
  <nav class="nav-links">
    <a href="/#products" >{{ __('app.nav.products') }}</a><a href="/#how">{{ __('app.nav.how') }}</a><a href="/#areas">{{ __('app.nav.areas') }}</a><a href="/#contact">{{ __('app.nav.contact') }}</a>
  </nav>
  <span class="spacer"></span>
  @auth
    <a class="btn btn-gold btn-sm" href="{{ auth()->user()->role->home() }}">{{ auth()->user()->isStaff() ? __('app.nav.dashboard') : __('app.nav.portal') }}</a>
  @else
    <a class="btn btn-text" style="color:#fff" href="{{ route('login') }}" wire:navigate>{{ __('app.auth.login') }}</a>
    <a class="btn btn-gold btn-sm" href="{{ route('register') }}" wire:navigate>{{ __('app.auth.register') }}</a>
  @endauth
</header>
<div class="gold-line"></div>
{{ $slot }}
@include('layouts.partials-toast')
@livewireScripts
</body></html>
