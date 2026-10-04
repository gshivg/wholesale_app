<div class="row" style="gap:0">
  @if($cart)
    <a class="icon-btn" href="{{ route('portal.cart') }}" wire:navigate title="Cart"><span class="material-symbols-outlined">shopping_cart</span>@if($cartCount)<span class="dot">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>@endif</a>
  @endif
  <a class="icon-btn" href="{{ route('notifications') }}" wire:navigate title="Notifications"><span class="material-symbols-outlined">notifications</span>@if($unread)<span class="dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif</a>
</div>
