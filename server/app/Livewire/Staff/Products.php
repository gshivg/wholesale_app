<?php

namespace App\Livewire\Staff;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.staff')]
#[Title('Products')]
class Products extends Component
{
    use WithFileUploads, WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $cat = '';
    public bool $editing = false;
    public ?int $productId = null;
    public string $name = '', $brand = '', $description = '', $hsn_code = '', $accent = '#1B3A8C';
    public string $gst_rate = '12';
    public ?int $category_id = null;
    public bool $is_featured = false, $is_active = true;
    public $image;
    public array $variants = [];
    public string $newCategory = '';

    public function mount(): void { abort_unless(auth()->user()->isSuperAdmin(), 403); }
    public function updated($n): void { if (in_array($n, ['search', 'cat'])) { $this->resetPage(); } }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('productId', 'name', 'brand', 'description', 'hsn_code', 'image', 'is_featured');
        $this->accent = '#1B3A8C'; $this->gst_rate = '12'; $this->is_active = true;
        $this->category_id = Category::orderBy('sort_order')->value('id');
        $this->variants = [$this->blankVariant()];
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $this->resetValidation();
        $p = Product::with('variants')->findOrFail($id);
        $this->productId = $p->id;
        $this->fill(['name' => $p->name, 'brand' => (string) $p->brand, 'description' => (string) $p->description, 'hsn_code' => (string) $p->hsn_code, 'accent' => $p->accent ?? '#1B3A8C',
            'gst_rate' => (string) (float) $p->gst_rate, 'category_id' => $p->category_id, 'is_featured' => $p->is_featured, 'is_active' => $p->is_active]);
        $this->image = null;
        $this->variants = $p->variants->map(fn ($v) => ['id' => $v->id, 'sku' => $v->sku, 'label' => $v->label, 'packs_per_case' => $v->packs_per_case, 'price' => (float) $v->price, 'mrp' => (float) $v->mrp,
            'min_qty' => $v->min_qty, 'max_qty' => $v->max_qty, 'is_active' => $v->is_active])->all();
        $this->editing = true;
    }

    private function blankVariant(): array
    {
        return ['id' => null, 'sku' => '', 'label' => '', 'packs_per_case' => 24, 'price' => '', 'mrp' => '', 'min_qty' => 1, 'max_qty' => 500, 'is_active' => true];
    }

    public function addVariant(): void { $this->variants[] = $this->blankVariant(); }
    public function removeVariant(int $i): void { unset($this->variants[$i]); $this->variants = array_values($this->variants); }

    public function addCategory(): void
    {
        $this->validate(['newCategory' => 'required|max:60']);
        Category::create(['name' => $this->newCategory, 'slug' => Str::slug($this->newCategory), 'sort_order' => Category::max('sort_order') + 1, 'icon' => 'category']);
        $this->newCategory = '';
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|max:150', 'category_id' => 'required|exists:categories,id', 'brand' => 'nullable|max:80', 'hsn_code' => 'nullable|max:8',
            'gst_rate' => 'required|in:' . implode(',', config('shared.gst.allowedRates')), 'accent' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/', 'image' => 'nullable|image|max:4096',
            'variants' => 'required|array|min:1',
            'variants.*.label' => 'required|max:80', 'variants.*.packs_per_case' => 'required|integer|min:1',
            'variants.*.price' => 'required|numeric|min:1', 'variants.*.mrp' => 'required|numeric|gte:variants.*.price',
            'variants.*.min_qty' => 'required|integer|min:1', 'variants.*.max_qty' => 'required|integer|gte:variants.*.min_qty',
        ], ['variants.*.mrp.gte' => 'MRP must be at least the price.']);

        DB::transaction(function () {
            $data = ['category_id' => $this->category_id, 'name' => $this->name, 'brand' => $this->brand ?: null, 'description' => $this->description ?: null, 'hsn_code' => $this->hsn_code ?: null,
                'gst_rate' => $this->gst_rate, 'accent' => $this->accent, 'is_featured' => $this->is_featured, 'is_active' => $this->is_active];
            if ($this->image) { $data['image_path'] = $this->image->store('products', 'public'); }
            if ($this->productId) {
                $p = Product::findOrFail($this->productId);
                $p->update($data);
            } else {
                $p = Product::create($data + ['slug' => $this->uniqueSlug($this->name)]);
            }
            $keep = [];
            foreach ($this->variants as $v) {
                $row = ['label' => $v['label'], 'packs_per_case' => $v['packs_per_case'], 'price' => $v['price'], 'mrp' => $v['mrp'], 'min_qty' => $v['min_qty'], 'max_qty' => $v['max_qty'], 'is_active' => (bool) ($v['is_active'] ?? true)];
                $variant = ! empty($v['id']) ? $p->variants()->findOrFail($v['id']) : null;
                if ($variant) {
                    $variant->update($row);
                } else {
                    $variant = $p->variants()->create($row + ['sku' => $this->sku($p, $v)]);
                }
                $keep[] = $variant->id;
            }
            // Variants removed in the editor are deactivated (order history keeps pointing at them).
            $p->variants()->whereNotIn('id', $keep)->update(['is_active' => false]);
            AuditLog::record($this->productId ? 'product.updated' : 'product.created', $p);
        });
        $this->editing = false;
        $this->dispatch('toast', message: 'Product saved', kind: 'success');
    }

    private function uniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $i = 1;
        while (Product::where('slug', $slug)->exists()) { $slug = Str::slug($name) . '-' . ++$i; }

        return $slug;
    }

    private function sku(Product $p, array $v): string
    {
        $base = $v['sku'] ?: strtoupper(Str::slug($p->name . '-' . $v['packs_per_case'] . '-' . Str::before($v['label'], ' ')));
        $sku = $base;
        $i = 1;
        while (\App\Models\ProductVariant::where('sku', $sku)->exists()) { $sku = $base . '-' . ++$i; }

        return $sku;
    }

    public function toggle(int $id): void
    {
        $p = Product::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
    }

    public function render()
    {
        $q = Product::with('category', 'variants')->orderBy('name');
        if ($this->cat !== '') { $q->where('category_id', $this->cat); }
        if ($this->search !== '') { $q->whereRaw('lower(name) like ?', ['%' . mb_strtolower($this->search) . '%']); }

        return view('livewire.staff.products', ['products' => $q->paginate(12), 'categories' => Category::orderBy('sort_order')->get(), 'rates' => config('shared.gst.allowedRates')]);
    }
}
