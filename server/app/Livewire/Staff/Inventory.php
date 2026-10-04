<?php

namespace App\Livewire\Staff;

use App\Models\Hub;
use App\Models\HubStock;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.staff')]
#[Title('Inventory')]
class Inventory extends Component
{
    use WithPagination;

    #[Url] public ?int $hub = null;
    #[Url(as: 'q')] public string $search = '';
    #[Url] public bool $lowOnly = false;
    public array $set = [];
    public bool $transferring = false;
    public ?int $tVariant = null, $tTo = null;
    public string $tQty = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->isSuperAdmin(), 403);
        $this->hub ??= Hub::where('type', 'main')->value('id');
    }

    public function updated($n): void { if (in_array($n, ['search', 'hub', 'lowOnly'])) { $this->resetPage(); } }

    public function saveQty(int $variantId): void
    {
        $v = $this->set[$variantId] ?? null;
        if ($v === null || ! is_numeric($v) || $v < 0) {
            $this->dispatch('toast', message: 'Enter a valid quantity', kind: 'error');

            return;
        }
        app(StockService::class)->adjust($this->hub, $variantId, (int) $v, auth()->user(), 'Manual adjustment');
        unset($this->set[$variantId]);
        $this->dispatch('toast', message: 'Stock updated', kind: 'success');
    }

    public function transfer(): void
    {
        $this->validate(['tVariant' => 'required|exists:product_variants,id', 'tTo' => 'required|exists:hubs,id|different:hub', 'tQty' => 'required|integer|min:1']);
        try {
            app(StockService::class)->transfer(Hub::find($this->hub), Hub::find($this->tTo), $this->tVariant, (int) $this->tQty, auth()->user());
        } catch (\RuntimeException $e) {
            $this->addError('tQty', $e->getMessage());

            return;
        }
        $this->transferring = false;
        $this->reset('tVariant', 'tTo', 'tQty');
        $this->dispatch('toast', message: 'Stock transferred', kind: 'success');
    }

    public function render()
    {
        $q = ProductVariant::with('product')->where('is_active', true)->orderBy('product_id');
        if ($this->search !== '') { $q->whereHas('product', fn ($p) => $p->whereRaw('lower(name) like ?', ['%' . mb_strtolower($this->search) . '%'])); }
        $stocks = HubStock::where('hub_id', $this->hub)->get()->keyBy('product_variant_id');
        if ($this->lowOnly) {
            $q->whereIn('id', $stocks->filter(fn ($s) => $s->isLow())->keys()->push(0));
        }
        $hubs = Hub::withCount('orders')->get();
        $summary = HubStock::selectRaw('hub_id, sum(quantity) qty, sum(reserved) res, sum(case when quantity - reserved <= low_stock_at then 1 else 0 end) low')->groupBy('hub_id')->get()->keyBy('hub_id');

        return view('livewire.staff.inventory', [
            'variants' => $q->paginate(14), 'stocks' => $stocks, 'hubs' => $hubs, 'summary' => $summary, 'current' => $hubs->firstWhere('id', $this->hub),
            'allVariants' => ProductVariant::with('product')->where('is_active', true)->get(),
            'movements' => StockMovement::with('variant.product', 'hub', 'user')->latest()->limit(8)->get(),
        ]);
    }
}
