<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Models\Cart;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private PaymentService $payments, private InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $q = $request->user()->orders()->withCount('items')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }
        if ($request->boolean('active')) {
            $q->whereNotIn('status', ['delivered', 'cancelled', 'returned']);
        }
        $page = $q->paginate(15);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($o) => Presenter::orderSummary($o))->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['payment_method' => 'required|string', 'notes' => 'nullable|string|max:500']);
        $order = $this->orders->place($request->user(), $data['payment_method'], $data['notes'] ?? null);
        $out = ['data' => Presenter::order($order)];
        if ($order->payment_method === 'online') {
            $out['payment'] = $this->intent($this->payments->createIntent($order));
        }

        return response()->json($out, 201);
    }

    public function show(Request $request, int $order): JsonResponse
    {
        return response()->json(['data' => Presenter::order($this->mine($request, $order))]);
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:300']);
        $o = $this->orders->transition($this->mine($request, $order), 'cancelled', $request->user(), ['note' => $data['reason'] ?? null]);

        return response()->json(['data' => Presenter::order($o)]);
    }

    public function reorder(Request $request, int $order): JsonResponse
    {
        $o = $this->mine($request, $order);
        $cart = Cart::forUser($request->user());
        $added = 0;
        foreach ($o->items as $i) {
            if ($i->variant?->is_active) {
                $cart->items()->updateOrCreate(['product_variant_id' => $i->product_variant_id], ['quantity' => $i->quantity]);
                $added++;
            }
        }

        return response()->json(['message' => "{$added} item(s) added to your cart."]);
    }

    public function pay(Request $request, int $order): JsonResponse
    {
        $o = $this->mine($request, $order);
        abort_unless($o->payment_method === 'online', 422, 'This order is not an online-payment order.');

        return response()->json(['payment' => $this->intent($this->payments->createIntent($o))]);
    }

    public function confirmPayment(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->order->user_id === $request->user()->id, 404);
        $data = $request->validate(['method' => 'required|in:card,upi', 'instrument' => 'required|string|max:40']);
        try {
            $p = $this->payments->confirm($payment, $data['method'], $data['instrument']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['payment' => ['id' => $p->id, 'status' => $p->status], 'data' => Presenter::order($p->order->fresh())]);
    }

    public function invoiceLink(Request $request, int $order): JsonResponse
    {
        $o = $this->mine($request, $order);
        abort_unless($this->invoices->available($o), 404, 'Invoice is not available yet.');

        return response()->json(['url' => URL::temporarySignedRoute('invoice.download', now()->addMinutes(15), ['order' => $o->id])]);
    }

    private function mine(Request $request, int $id): Order
    {
        return $request->user()->orders()->with('items.variant')->findOrFail($id);
    }

    private function intent(Payment $p): array
    {
        return [
            'id' => $p->id, 'reference' => $p->reference, 'amount' => (float) $p->amount, 'currency' => $p->currency,
            'gateway' => $p->gateway,
            'test_instruments' => collect(config('shared.payments.testCards'))->map(fn ($o, $k) => ['value' => $k, 'outcome' => $o])->values(),
        ];
    }
}
