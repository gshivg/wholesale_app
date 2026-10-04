<div class="page stack-lg" wire:poll.10s>
  <div><a class="btn btn-text" href="{{ route('portal.tickets') }}" wire:navigate><span class="material-symbols-outlined">arrow_back</span>Support</a>
    <div class="row between wrap"><div><h1 class="headline-s">{{ $t->subject }}</h1><div class="muted">{{ $t->number }} · {{ $t->categoryLabel() }}@if($t->order) · Order <a href="{{ route('portal.orders.show', $t->order) }}" wire:navigate>{{ $t->order->number }}</a>@endif · Agent: {{ $t->agent?->name ?? 'being assigned' }}</div></div>
      <span class="badge {{ ['open' => 'gold', 'in_progress' => 'primary', 'resolved' => 'success', 'closed' => 'neutral'][$t->status] }}">{{ str_replace('_', ' ', $t->status) }}</span></div></div>
  <div class="card"><x-m3.chat :messages="$messages" /></div>

  @if(in_array($t->status, ['resolved', 'closed']))
    <div class="card gold row between wrap"><div><b>This ticket is {{ $t->status }}.</b> Reply below to reopen it.</div>
      <div class="row">@foreach([1,2,3,4,5] as $n)<button class="icon-btn" wire:click="rate({{ $n }})" title="{{ $n }} star"><span style="color:#B8860B;font-size:24px">{{ ($t->rating ?? 0) >= $n ? '★' : '☆' }}</span></button>@endforeach</div></div>
  @endif

  <form class="card stack" wire:submit="send">
    <x-m3.textarea label="Your reply" wire:model="body" />
    <div class="row between wrap"><input type="file" wire:model="photos" multiple accept="image/*">
      <div class="row">@if($t->status !== 'closed')<button type="button" class="btn btn-text" wire:click="close" wire:confirm="Close this ticket?">Close ticket</button>@endif<button class="btn btn-filled" wire:loading.attr="disabled"><span class="material-symbols-outlined">send</span>Send</button></div></div>
  </form>
</div>
