@php
  use App\Support\StaffNav;
  $u = auth()->user();
  $nav = StaffNav::for($u);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>@include('layouts.partials-head')<title>{{ $title ?? $u->role->label() }} · {{ config('shared.brand.name') }}</title></head>
<body>
<header class="app-bar">
  <a href="{{ $u->role->home() }}" class="brand" wire:navigate><span class="logo">R</span>{{ config('shared.brand.name') }} <span class="badge gold" style="margin-left:8px">{{ $u->role->label() }}</span></a>
  <span class="spacer"></span>
  @if($u->hub)<span class="chip gold" title="Your hub"><span class="material-symbols-outlined icon-sm">warehouse</span>{{ $u->hub->name }}</span>@endif
  <livewire:portal.badges :cart="false" />
  <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="{{ __('app.auth.logout') }}"><span class="material-symbols-outlined">logout</span></button></form>
</header>
<div class="gold-line"></div>
<div class="shell">
  <aside class="drawer">
    <div class="section">{{ $u->name }}</div>
    @foreach($nav as $item)
      <a class="nav-item {{ request()->is($item['match']) ? 'active' : '' }}" href="{{ $item['href'] }}" wire:navigate><span class="material-symbols-outlined">{{ $item['icon'] }}</span>{{ $item['label'] }}</a>
    @endforeach
  </aside>
  <main class="has-bottom-nav">{{ $slot }}</main>
</div>
<nav class="bottom-nav">
  @foreach(array_slice($nav, 0, 4) as $item)
    <a class="{{ request()->is($item['match']) ? 'active' : '' }}" href="{{ $item['href'] }}" wire:navigate><span class="pill"><span class="material-symbols-outlined">{{ $item['icon'] }}</span></span>{{ $item['label'] }}</a>
  @endforeach
</nav>
@include('layouts.partials-toast')
@livewireScripts
</body></html>
