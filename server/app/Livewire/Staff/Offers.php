<?php

namespace App\Livewire\Staff;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\DiscountSlab;
use App\Models\Product;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.staff')]
#[Title('Offers & coupons')]
class Offers extends Component
{
    #[Url] public string $tab = 'coupons';
    public ?string $form = null;      // coupon | deal | slab
    public ?int $editId = null;

    // coupon
    public string $code = '', $title = '', $description = '', $ctype = 'percent', $value = '', $max_discount = '', $min_order = '0', $usage_limit = '', $per_user_limit = '1', $starts_at = '', $ends_at = '';
    public bool $is_public = true, $first_order_only = false, $is_active = true;
    // deal
    public string $dtype = 'percent', $dvalue = '', $buy_qty = '', $free_qty = '', $dmin_qty = '1', $target = 'all', $target_id = '';
    // slab
    public string $min_subtotal = '', $percent = '';

    public function mount(): void { abort_unless(auth()->user()->isSuperAdmin(), 403); }

    public function open(string $form, ?int $id = null): void
    {
        $this->resetValidation();
        $this->form = $form;
        $this->editId = $id;
        $this->reset('code', 'title', 'description', 'value', 'max_discount', 'usage_limit', 'starts_at', 'ends_at', 'dvalue', 'buy_qty', 'free_qty', 'target_id', 'min_subtotal', 'percent');
        $this->ctype = 'percent'; $this->min_order = '0'; $this->per_user_limit = '1'; $this->is_public = true; $this->first_order_only = false; $this->is_active = true;
        $this->dtype = 'percent'; $this->dmin_qty = '1'; $this->target = 'all';
        if (! $id) {
            if ($form === 'coupon') { $this->code = strtoupper(Str::random(8)); }

            return;
        }
        if ($form === 'coupon') {
            $c = Coupon::findOrFail($id);
            $this->fill(['code' => $c->code, 'title' => $c->title, 'description' => (string) $c->description, 'ctype' => $c->type, 'value' => (string) (float) $c->value, 'max_discount' => (string) ($c->max_discount ? (float) $c->max_discount : ''),
                'min_order' => (string) (float) $c->min_order, 'usage_limit' => (string) $c->usage_limit, 'per_user_limit' => (string) $c->per_user_limit, 'is_public' => $c->is_public, 'first_order_only' => $c->first_order_only,
                'is_active' => $c->is_active, 'starts_at' => $c->starts_at?->format('Y-m-d') ?? '', 'ends_at' => $c->ends_at?->format('Y-m-d') ?? '']);
        } elseif ($form === 'deal') {
            $d = Deal::findOrFail($id);
            $this->fill(['title' => $d->title, 'description' => (string) $d->description, 'dtype' => $d->type, 'dvalue' => (string) (float) $d->value, 'buy_qty' => (string) $d->buy_qty, 'free_qty' => (string) $d->free_qty, 'dmin_qty' => (string) $d->min_qty,
                'is_active' => $d->is_active, 'starts_at' => $d->starts_at?->format('Y-m-d') ?? '', 'ends_at' => $d->ends_at?->format('Y-m-d') ?? '']);
            [$this->target, $this->target_id] = $d->product_id ? ['product', (string) $d->product_id] : ($d->category_id ? ['category', (string) $d->category_id] : ['all', '']);
        } else {
            $s = DiscountSlab::findOrFail($id);
            $this->fill(['min_subtotal' => (string) (float) $s->min_subtotal, 'percent' => (string) (float) $s->percent, 'is_active' => $s->is_active]);
        }
    }

    public function saveCoupon(): void
    {
        $d = $this->validate([
            'code' => ['required', 'alpha_dash', 'max:30', 'unique:coupons,code' . ($this->editId ? ",{$this->editId}" : '')], 'title' => 'required|max:80', 'description' => 'nullable|max:200',
            'ctype' => 'required|in:percent,flat', 'value' => 'required|numeric|min:1' . ($this->ctype === 'percent' ? '|max:100' : ''), 'max_discount' => 'nullable|numeric|min:1', 'min_order' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1', 'per_user_limit' => 'required|integer|min:1', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);
        $row = ['code' => strtoupper($d['code']), 'title' => $d['title'], 'description' => $d['description'] ?: null, 'type' => $d['ctype'], 'value' => $d['value'], 'max_discount' => $d['max_discount'] ?: null,
            'min_order' => $d['min_order'], 'usage_limit' => $d['usage_limit'] ?: null, 'per_user_limit' => $d['per_user_limit'], 'is_public' => $this->is_public, 'first_order_only' => $this->first_order_only,
            'is_active' => $this->is_active, 'starts_at' => $d['starts_at'] ?: null, 'ends_at' => $d['ends_at'] ? $d['ends_at'] . ' 23:59:59' : null];
        $c = $this->editId ? tap(Coupon::findOrFail($this->editId))->update($row) : Coupon::create($row);
        AuditLog::record('coupon.saved', $c);
        $this->done();
    }

    public function saveDeal(): void
    {
        $rules = ['title' => 'required|max:80', 'description' => 'nullable|max:200', 'dtype' => 'required|in:percent,flat,bxgy', 'dmin_qty' => 'required|integer|min:1', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after_or_equal:starts_at'];
        $rules += $this->dtype === 'bxgy' ? ['buy_qty' => 'required|integer|min:1', 'free_qty' => 'required|integer|min:1'] : ['dvalue' => 'required|numeric|min:1' . ($this->dtype === 'percent' ? '|max:90' : '')];
        if ($this->target !== 'all') { $rules['target_id'] = 'required|integer'; }
        $d = $this->validate($rules);
        $row = ['title' => $d['title'], 'description' => $d['description'] ?: null, 'type' => $d['dtype'], 'value' => $d['dvalue'] ?? 0, 'buy_qty' => $d['buy_qty'] ?? null, 'free_qty' => $d['free_qty'] ?? null, 'min_qty' => $d['dmin_qty'],
            'product_id' => $this->target === 'product' ? $this->target_id : null, 'category_id' => $this->target === 'category' ? $this->target_id : null, 'product_variant_id' => null,
            'is_active' => $this->is_active, 'starts_at' => $d['starts_at'] ?: null, 'ends_at' => $d['ends_at'] ? $d['ends_at'] . ' 23:59:59' : null];
        $x = $this->editId ? tap(Deal::findOrFail($this->editId))->update($row) : Deal::create($row);
        AuditLog::record('deal.saved', $x);
        $this->done();
    }

    public function saveSlab(): void
    {
        $d = $this->validate(['min_subtotal' => 'required|numeric|min:1', 'percent' => 'required|numeric|min:0.1|max:50']);
        $row = ['min_subtotal' => $d['min_subtotal'], 'percent' => $d['percent'], 'is_active' => $this->is_active];
        $x = $this->editId ? tap(DiscountSlab::findOrFail($this->editId))->update($row) : DiscountSlab::create($row);
        AuditLog::record('slab.saved', $x);
        $this->done();
    }

    public function toggle(string $type, int $id): void
    {
        $m = ['coupon' => Coupon::class, 'deal' => Deal::class, 'slab' => DiscountSlab::class][$type]::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);
    }

    public function delete(string $type, int $id): void
    {
        if ($type === 'coupon') {
            $c = Coupon::findOrFail($id);
            $c->redemptions()->exists() ? $c->update(['is_active' => false]) : $c->delete();
        } else {
            ['deal' => Deal::class, 'slab' => DiscountSlab::class][$type]::whereKey($id)->delete();
        }
    }

    private function done(): void
    {
        $this->form = null;
        $this->dispatch('toast', message: 'Saved', kind: 'success');
    }

    public function render()
    {
        return view('livewire.staff.offers', [
            'coupons' => Coupon::orderByDesc('id')->get(), 'deals' => Deal::with('product', 'category')->orderByDesc('id')->get(), 'slabs' => DiscountSlab::orderBy('min_subtotal')->get(),
            'products' => Product::orderBy('name')->get(['id', 'name']), 'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
