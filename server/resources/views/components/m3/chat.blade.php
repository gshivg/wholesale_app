{{-- Ticket conversation. $messages: collection; $viewerIsStaff: bool --}}
@props(['messages', 'viewerIsStaff' => false])
<div class="chat">
  @foreach($messages as $m)
    @php $staff = $m->user?->isStaff() ?? true; $mine = $viewerIsStaff ? $staff : ! $staff; @endphp
    <div class="bubble {{ $m->is_internal ? 'internal' : ($mine ? 'me' : 'them') }}">
      <div class="who">{{ $m->is_internal ? '🔒 Internal note · ' : '' }}{{ $m->user?->name ?? 'System' }}@if($staff && ! $viewerIsStaff) (Support)@endif · {{ $m->created_at->format('d M, h:i A') }}</div>
      <div style="white-space:pre-wrap">{{ $m->body }}</div>
      @foreach($m->attachmentUrls() as $u)<a href="{{ $u }}" target="_blank"><img src="{{ $u }}" alt="attachment"></a>@endforeach
    </div>
  @endforeach
</div>
