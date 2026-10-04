@php use App\Support\Money; @endphp
<div class="page stack-lg">
  <div class="row between wrap"><div><h1 class="headline-m">Products</h1><div class="muted">Prices are per case, excluding GST.</div></div><button class="btn btn-gold" wire:click="create"><span class="material-symbols-outlined">add</span>New product</button></div>
  <div class="grid cols-3"><div class="field"><input type="search" placeholder=" " wire:model.live.debounce.300ms="search"><label>Search products</label></div>
    <x-m3.select label="Category" wire:model.live="cat"><option value="">All</option>@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</x-m3.select></div>
  <div class="card flat"><div class="table-wrap"><table class="data"><thead><tr><th>Product</th><th>Category</th><th>GST</th><th>Variants (per case)</th><th>Active</th><th></th></tr></thead><tbody>
    @foreach($products as $p)
      <tr wire:key="p{{ $p->id }}"><td><div class="row"><span style="width:12px;height:36px;border-radius:6px;background:{{ $p->accent }}"></span><div><div class="title-s">{{ $p->name }} @if($p->is_featured)<span class="badge gold">featured</span>@endif</div><div class="muted">{{ $p->brand }} · HSN {{ $p->hsn_code ?: '—' }}</div></div></div></td>
        <td>{{ $p->category->name }}</td><td>{{ rtrim(rtrim($p->gst_rate, '0'), '.') }}%</td>
        <td>@foreach($p->variants->where('is_active', true) as $v)<div class="muted">{{ $v->label }} · <b>{{ Money::fmt($v->price, true) }}</b> (min {{ $v->min_qty }})</div>@endforeach</td>
        <td><button class="chip {{ $p->is_active ? 'selected' : '' }}" wire:click="toggle({{ $p->id }})">{{ $p->is_active ? 'Active' : 'Hidden' }}</button></td>
        <td class="right"><button class="btn btn-tonal btn-sm" wire:click="edit({{ $p->id }})">Edit</button></td></tr>
    @endforeach</tbody></table></div></div>
  <x-m3.pager :p="$products" />

  @if($editing)
  <div class="scrim"><form class="dialog stack" style="max-width:820px" wire:submit="save">
    <h2 class="headline-s">{{ $productId ? 'Edit product' : 'New product' }}</h2>
    <div class="grid cols-2"><x-m3.input label="Product name" wire:model="name" /><x-m3.input label="Brand" wire:model="brand" /></div>
    <div class="grid cols-2">
      <div class="stack-sm"><x-m3.select label="Category" wire:model="category_id">@foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</x-m3.select>
        <div class="row"><x-m3.input class="grow" label="…or add category" wire:model="newCategory" /><button type="button" class="btn btn-tonal btn-sm" wire:click="addCategory">Add</button></div></div>
      <div class="stack-sm"><div class="grid cols-2"><x-m3.select label="GST rate %" wire:model="gst_rate">@foreach($rates as $r)<option value="{{ $r }}">{{ $r }}%</option>@endforeach</x-m3.select><x-m3.input label="HSN code" wire:model="hsn_code" /></div></div>
    </div>
    <x-m3.textarea label="Description" wire:model="description" />
    <div class="row wrap"><label class="row"><span class="label-l">Tile colour</span><input type="color" wire:model="accent"></label><label class="check"><input type="checkbox" wire:model="is_featured"> Featured</label><label class="check"><input type="checkbox" wire:model="is_active"> Active</label><input type="file" wire:model="image" accept="image/*"></div>
    <div class="divider"></div>
    <div class="row between"><h3 class="title-m">Variants (sold per case)</h3><button type="button" class="btn btn-tonal btn-sm" wire:click="addVariant"><span class="material-symbols-outlined">add</span>Add variant</button></div>
    @foreach($variants as $i => $v)
      <div class="card outlined stack" wire:key="var{{ $i }}">
        <div class="grid cols-3"><x-m3.input label="Label (e.g. 30g × 48 packs)" wire:model="variants.{{ $i }}.label" /><x-m3.input label="Packs per case" type="number" wire:model="variants.{{ $i }}.packs_per_case" /><x-m3.input label="SKU (auto if blank)" wire:model="variants.{{ $i }}.sku" :disabled="! empty($v['id'])" /></div>
        <div class="grid cols-4"><x-m3.input label="Price / case ₹" type="number" step="0.01" wire:model="variants.{{ $i }}.price" /><x-m3.input label="MRP / case ₹" type="number" step="0.01" wire:model="variants.{{ $i }}.mrp" /><x-m3.input label="Min cases" type="number" wire:model="variants.{{ $i }}.min_qty" /><x-m3.input label="Max cases" type="number" wire:model="variants.{{ $i }}.max_qty" /></div>
        <div class="row between"><label class="check"><input type="checkbox" wire:model="variants.{{ $i }}.is_active"> Active</label>@if(count($variants) > 1)<button type="button" class="btn btn-danger-text btn-sm" wire:click="removeVariant({{ $i }})">Remove</button>@endif</div>
      </div>
    @endforeach
    @if($errors->any())<div class="banner error"><span class="material-symbols-outlined">error</span><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>@endif
    <div class="actions"><button type="button" class="btn btn-text" wire:click="$set('editing', false)">Cancel</button><button class="btn btn-filled" wire:loading.attr="disabled">Save product</button></div>
  </form></div>
  @endif
</div>
