<?php

namespace App\Livewire\Staff;

use App\Models\AuditLog;
use App\Models\Hub;
use App\Models\HubStock;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shop;
use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Super-admin analytics: sales, fulfilment, customers, offers, support. */
#[Layout('layouts.staff')]
#[Title('Overview')]
class Overview extends Component
{
    #[Url]
    public int $days = 30;

    public function render()
    {
        $from = now()->subDays($this->days)->startOfDay();
        $valid = fn () => Order::where('orders.created_at', '>=', $from)->whereNotIn('orders.status', ['cancelled', 'returned']);

        $revenue = (float) $valid()->sum('orders.total');
        $orderCount = $valid()->count();

        // Daily series (fill gaps)
        $series = $valid()->selectRaw('orders.created_at::date as d, sum(orders.total) as revenue, count(*) as orders')->groupBy('d')->orderBy('d')->get()->keyBy(fn ($r) => (string) $r->d);
        $chart = collect(range($this->days - 1, 0))->map(function ($i) use ($series) {
            $d = now()->subDays($i)->toDateString();

            return ['label' => now()->subDays($i)->format('d M'), 'revenue' => (float) ($series[$d]->revenue ?? 0), 'orders' => (int) ($series[$d]->orders ?? 0)];
        });
        if ($this->days > 31) {
            $chart = $chart->chunk((int) ceil($this->days / 30))->map(fn ($c) => ['label' => $c->first()['label'], 'revenue' => $c->sum('revenue'), 'orders' => $c->sum('orders')])->values();
        }

        $byStage = Order::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $stageRows = collect(config('shared.orders.stages'))->map(fn ($s) => ['label' => $s['label'], 'count' => (int) ($byStage[$s['key']] ?? 0)])
            ->push(['label' => 'Cancelled', 'count' => (int) ($byStage['cancelled'] ?? 0)])->push(['label' => 'Returned', 'count' => (int) ($byStage['returned'] ?? 0)]);

        $items = OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')->where('orders.created_at', '>=', $from)->whereNotIn('orders.status', ['cancelled', 'returned']);
        $topProducts = (clone $items)->selectRaw('order_items.name, sum(order_items.quantity) cases, sum(order_items.total) revenue')->groupBy('order_items.name')->orderByDesc('revenue')->limit(6)->get();
        $categories = (clone $items)->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->join('products', 'products.id', '=', 'product_variants.product_id')->join('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw('categories.name, sum(order_items.total) revenue')->groupBy('categories.name')->orderByDesc('revenue')->get();
        $topShops = $valid()->join('shops', 'shops.id', '=', 'orders.shop_id')->selectRaw('shops.shop_name, shops.city, count(*) orders, sum(orders.total) revenue')->groupBy('shops.shop_name', 'shops.city')->orderByDesc('revenue')->limit(6)->get();
        $byHub = $valid()->join('hubs', 'hubs.id', '=', 'orders.hub_id')->selectRaw('hubs.name, count(*) orders, sum(orders.total) revenue')->groupBy('hubs.name')->orderByDesc('revenue')->get();
        $byPayment = $valid()->selectRaw('orders.payment_method, count(*) c, sum(orders.total) s')->groupBy('orders.payment_method')->get();

        $offers = $valid()->selectRaw('coalesce(sum(deal_discount),0) deals, coalesce(sum(slab_discount),0) slabs, coalesce(sum(coupon_discount),0) coupons, count(coupon_id) coupon_orders')->first();

        // Fulfilment speed (minutes between stages, delivered orders in window)
        $ful = Order::where('created_at', '>=', $from)->whereNotNull('delivered_at')->selectRaw(
            "avg(extract(epoch from (confirmed_at - created_at))/60) to_confirm,
             avg(extract(epoch from (packed_at - confirmed_at))/60) to_pack,
             avg(extract(epoch from (dispatched_at - packed_at))/60) to_dispatch,
             avg(extract(epoch from (delivered_at - dispatched_at))/60) to_deliver,
             avg(extract(epoch from (delivered_at - created_at))/3600) total_hours")->first();

        $tickets = Ticket::where('created_at', '>=', $from);
        $ticketStats = [
            'open' => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
            'unassigned' => Ticket::whereNull('assigned_to')->whereIn('status', ['open', 'in_progress'])->count(),
            'new' => (clone $tickets)->count(),
            'avg_first_response' => (clone $tickets)->whereNotNull('first_response_at')->selectRaw('avg(extract(epoch from (first_response_at - created_at))/60) m')->value('m'),
            'avg_resolution' => (clone $tickets)->whereNotNull('resolved_at')->selectRaw('avg(extract(epoch from (resolved_at - created_at))/3600) h')->value('h'),
            'csat' => (clone $tickets)->whereNotNull('rating')->avg('rating'),
            'by_category' => (clone $tickets)->selectRaw('category, count(*) c')->groupBy('category')->orderByDesc('c')->pluck('c', 'category'),
        ];

        return view('livewire.staff.overview', [
            'revenue' => $revenue,
            'orderCount' => $orderCount,
            'aov' => $orderCount ? $revenue / $orderCount : 0,
            'chart' => $chart,
            'maxRev' => max(1, $chart->max('revenue')),
            'stageRows' => $stageRows,
            'topProducts' => $topProducts,
            'categories' => $categories,
            'topShops' => $topShops,
            'byHub' => $byHub,
            'byPayment' => $byPayment,
            'offers' => $offers,
            'ful' => $ful,
            'ticketStats' => $ticketStats,
            'pendingShops' => Shop::where('status', 'pending')->count(),
            'activeShops' => Shop::where('status', 'approved')->count(),
            'newShops' => Shop::where('created_at', '>=', $from)->count(),
            'creditOutstanding' => (float) Shop::sum('credit_used'),
            'lowStock' => HubStock::whereRaw('(quantity - reserved) <= low_stock_at')->count(),
            'unpaidOnline' => Order::where('payment_method', 'online')->where('payment_status', '!=', 'paid')->whereIn('status', ['placed', 'confirmed'])->count(),
            'recent' => AuditLog::with('user')->latest()->limit(8)->get(),
            'hubs' => Hub::count(),
        ]);
    }
}
