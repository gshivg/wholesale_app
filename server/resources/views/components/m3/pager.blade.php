@props(['p'])
@if($p->hasPages())
<div class="row between mt-3">
  <button class="btn btn-tonal btn-sm" wire:click="previousPage" @disabled($p->onFirstPage())><span class="material-symbols-outlined">chevron_left</span>Prev</button>
  <span class="muted">Page {{ $p->currentPage() }} of {{ $p->lastPage() }} · {{ $p->total() }} total</span>
  <button class="btn btn-tonal btn-sm" wire:click="nextPage" @disabled(! $p->hasMorePages())>Next<span class="material-symbols-outlined">chevron_right</span></button>
</div>
@endif
