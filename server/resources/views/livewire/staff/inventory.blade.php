<div class="page stack-lg">
  <div class="row between wrap"><div><h1 class="headline-m">Inventory & hubs</h1><div class="muted">Umbrella model: the Prayagraj main warehouse feeds the district distribution centres.</div></div>
    <button class="btn btn-gold" wire:click="$set('transferring', true)"><span class="material-symbols-outlined">swap_horiz</span>Transfer stock</button></div>
  <div class="grid cols-4">
    @foreach($hubs as $h)
      @php $s = $summary[$h->id] ?? null; @endphp
      <button class="card stat-card stat" style="text-align:left;border:0;cursor:pointer;font:inherit;{{ $hub === $h->id ? 'outline:3px solid var(--md-tertiary)' : '' }}" wire:click="$set('hub', {{ $h->id }})">
        <div class="ic"><span class="material-symbols-outlined">{{ $h->isMain() ? 'warehouse' : 'local_shipping' }}</span></div>
        <div class="title-s">{{ $h->name }}</div><div class="v">{{ number_format($s->qty ?? 0) }}</div><div class="l">cases · {{ $s->res ?? 0 }} reserved · <span class="{{ ($s->low ?? 0) ? 'error' : '' }}">{{ $s->low ?? 0 }} low</span></div>
      </button>
    @endforeach
  </div>
  <div class="row wrap"><div class="field grow"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Search product in {{ $current?->name }}</label></div><label class="check"><input type="checkbox" wire:model.live="lowOnly"> Low stock only</label></div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th class="right">On hand</th><th class="right">Reserved</th><th class="right">Available</th><th>Set quantity</th></tr></thead><tbody>
    @foreach($variants as $v)
      @php $st = $stocks[$v->id] ?? null; @endphp
      <tr wire:key="v{{ $v->id }}"><td><div class="title-s">{{ $v->product->name }}</div><div class="muted">{{ $v->label }} · {{ $v->sku }}</div></td>
        <td class="right">{{ $st->quantity ?? 0 }}</td><td class="right">{{ $st->reserved ?? 0 }}</td>
        <td class="right"><span class="badge {{ ($st && $st->isLow()) || ! $st ? 'error' : 'success' }}">{{ $st->available ?? 0 }}</span></td>
        <td><div class="row"><input type="number" min="0" style="width:90px;height:40px;border:1px solid var(--md-outline);border-radius:8px;padding:0 8px" placeholder="{{ $st->quantity ?? 0 }}" wire:model="set.{{ $v->id }}"><button class="btn btn-tonal btn-sm" wire:click="saveQty({{ $v->id }})">Save</button></div></td></tr>
    @endforeach</tbody></table></div></div>
  <x-m3.pager :p="$variants" />
  <div class="card"><h2 class="title-m mb-2">Recent stock movements</h2>@foreach($movements as $m)<div class="list-item"><div class="grow"><b>{{ str_replace('_', ' ', $m->type) }}</b> · {{ $m->variant->product->name }} ({{ $m->variant->label }}) <span class="muted">· {{ $m->hub->code }} · {{ $m->quantity }} cases</span></div><span class="muted">{{ $m->user?->name ?? 'system' }} · {{ $m->created_at->diffForHumans() }}</span></div>@endforeach</div>

  @if($transferring)<div class="scrim"><form class="dialog stack" wire:submit="transfer"><h2 class="headline-s">Transfer from {{ $current?->name }}</h2>
    <x-m3.select label="Product variant" wire:model="tVariant"><option value="">Select…</option>@foreach($allVariants as $v)<option value="{{ $v->id }}">{{ $v->product->name }} — {{ $v->label }}</option>@endforeach</x-m3.select>
    <x-m3.select label="Destination hub" wire:model="tTo"><option value="">Select…</option>@foreach($hubs->where('id', '!=', $hub) as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach</x-m3.select>
    <x-m3.input label="Cases" type="number" wire:model="tQty" />
    <div class="actions"><button type="button" class="btn btn-text" wire:click="$set('transferring', false)">Cancel</button><button class="btn btn-filled">Transfer</button></div></form></div>@endif
</div>
