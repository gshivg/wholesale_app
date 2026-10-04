@props(['status'])
<span class="badge {{ \App\Support\OrderStatus::tone($status) }}">{{ \App\Support\OrderStatus::label($status) }}</span>
