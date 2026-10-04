<?php

namespace App\Livewire\Portal;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Deal;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StockService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.portal')]
#[Title('Shop')]
class Catalog extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';
    #[Url(as: 'cat', except: '')]
    public string $category = '';
    public array $qty = [];

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }

    public function setCategory(string $id): void
    {
        $this->category = $this->category === $id ? '' : $id;
        $this->resetPage();
    }

    public function step(int $variantId, int $delta, int $min): void
    {
        $this->qty[$variantId] = max($min, (int) ($this->qty[$variantId] ?? $min) + $delta);
    }

    public function add(int $variantId): void
    {
        $user = auth()->user();
        if (! $user->canOrder()) {
            $this->dispatch('toast', message: 'Ordering unlocks once your account is approved.', kind: 'error');

            return;
        }
        $v = ProductVariant::with('product')->where('is_active', true)->findOrFail($variantId);
        $add = max($v->min_qty, (int) ($this->qty[$variantId] ?? $v->min_qty));
        $cart = Cart::forUser($user);
        $existing = (int) $cart->items()->where('product_variant_id', $v->id)->value('quantity');
        $new = min($v->max_qty, $existing + $add);
        $cart->items()->updateOrCreate(['product_variant_id' => $v->id], ['quantity' => $new]);
        unset($this->qty[$variantId]);
        $this->dispatch('cart-updated');
        $this->dispatch('toast', message: "{$v->product->name}: {$new} case(s) in cart", kind: 'success');
    }

    public function render()
    {
        $user = auth()->user();
        $q = Product::active()->with(['category', 'variants' => fn ($v) => $v->where('is_active', true)]);
        if ($this->category !== '') {
            $q->where('category_id', (int) $this->category);
        }
        if ($this->search !== '') {
            $t = '%' . mb_strtolower($this->search) . '%';
            $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$t])->orWhereRaw('lower(brand) like ?', [$t]));
        }
        $products = $q->orderByDesc('is_featured')->orderBy('name')->paginate(12);
        $hub = $user->shop?->hub_id;
        $stock = app(StockService::class);
        $inCart = $user->cart ? $user->cart->items()->pluck('quantity', 'product_variant_id')->all() : [];

        return view('livewire.portal.catalog', [
            'products' => $products,
            'categories' => Category::where('is_active', true)->orderBy('sort_order')->get(),
            'deals' => Deal::live()->get(),
            'availability' => fn ($v) => $hub ? $stock->available($hub, $v->id) : 0,
            'inCart' => $inCart,
            'canOrder' => $user->canOrder(),
        ]);
    }
}
