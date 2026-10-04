<div class="page stack-lg">
  <div><h1 class="headline-m">Audit log</h1><div class="muted">Who did what, when — order stages, approvals, catalogue and team changes.</div></div>
  <div class="field" style="max-width:360px"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Filter by action (e.g. order.packed)</label></div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>When</th><th>Action</th><th>By</th><th>Subject</th><th>Details</th></tr></thead><tbody>
    @foreach($logs as $l)<tr><td class="muted nowrap">{{ $l->created_at->format('d M Y, h:i:s A') }}</td><td><span class="badge primary">{{ $l->action }}</span></td><td>{{ $l->user?->name ?? 'system' }}</td><td class="muted">{{ $l->subject_type ? class_basename($l->subject_type) . ' #' . $l->subject_id : '—' }}</td><td class="muted">{{ $l->meta ? json_encode($l->meta) : '' }}</td></tr>@endforeach
  </tbody></table></div></div>
  <x-m3.pager :p="$logs" />
</div>
