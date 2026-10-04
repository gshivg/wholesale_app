<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Models\Category;
use App\Models\Deal;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::where('is_active', true)->withCount(['products' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug, 'icon' => $c->icon, 'product_count' => $c->products_count])]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['category' => 'nullable|integer', 'q' => 'nullable|string|max:80', 'featured' => 'nullable|boolean']);
        $q = Product::active()->with(['category', 'variants' => fn ($v) => $v->where('is_active', true)]);
        if ($request->filled('category')) {
            $q->where('category_id', $request->integer('category'));
        }
        if ($request->filled('q')) {
            $term = '%' . mb_strtolower($request->string('q')) . '%';
            $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$term])->orWhereRaw('lower(brand) like ?', [$term]));
        }
        if ($request->boolean('featured')) {
            $q->where('is_featured', true);
        }
        $page = $q->orderByDesc('is_featured')->orderBy('name')->paginate(20);
        $hub = $request->user()->shop?->hub_id;
        $deals = Deal::live()->get();

        return response()->json([
            'data' => $page->getCollection()->map(fn ($p) => Presenter::product($p, $hub, $deals))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        return response()->json(['data' => Presenter::product($product, $request->user()->shop?->hub_id)]);
    }
}
