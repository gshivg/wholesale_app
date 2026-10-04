<?php

namespace App\Livewire\Portal;

use App\Models\Cart;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Support\Facades\URL as SignedUrl;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Order')]
class OrderShow extends Component
{
    public Order $order;
    #[Url]
    public bool $pay = false;
    public ?int $paymentId = null;
    public string $method = 'card';
    public string $instrument = '4111 1111 1111 1111';
    public bool $confirmCancel = false;
    public string $reason = '';

    public function mount(Order $order): void
    {
        abort_unless($order->user_id === auth()->id(), 404);
        $this->order = $order;
        if ($this->pay && $this->canPay()) {
            $this->startPayment();
        }
    }

    private function canPay(): bool
    {
        return $this->order->payment_method === 'online' && $this->order->payment_status !== 'paid' && in_array($this->order->status, ['placed', 'confirmed'], true);
    }

    public function startPayment(): void
    {
        $this->paymentId = app(PaymentService::class)->createIntent($this->order)->id;
        $this->method = 'card';
        $this->instrument = '4111 1111 1111 1111';
    }

    public function useInstrument(string $method, string $value): void
    {
        $this->method = $method;
        $this->instrument = $value;
    }

    public function confirmPayment(): void
    {
        $payment = $this->order->payments()->findOrFail($this->paymentId);
        try {
            $p = app(PaymentService::class)->confirm($payment, $this->method, $this->instrument);
        } catch (\InvalidArgumentException $e) {
            $this->addError('instrument', $e->getMessage());

            return;
        }
        $this->paymentId = null;
        $this->order = $this->order->fresh();
        $this->dispatch('toast', message: $p->status === 'paid' ? 'Payment successful' : 'Payment failed — you can retry', kind: $p->status === 'paid' ? 'success' : 'error');
    }

    public function closePayment(): void
    {
        $this->paymentId = null;
    }

    public function cancel(): void
    {
        $this->order = app(OrderService::class)->transition($this->order, 'cancelled', auth()->user(), ['note' => $this->reason ?: null]);
        $this->confirmCancel = false;
        $this->dispatch('toast', message: 'Order cancelled', kind: 'success');
    }

    public function reorder()
    {
        $cart = Cart::forUser(auth()->user());
        foreach ($this->order->items()->with('variant')->get() as $i) {
            if ($i->variant?->is_active) {
                $cart->items()->updateOrCreate(['product_variant_id' => $i->product_variant_id], ['quantity' => $i->quantity]);
            }
        }
        $this->dispatch('cart-updated');

        return redirect()->route('portal.cart');
    }

    public function render()
    {
        $this->order->refresh()->load('items', 'events', 'agent', 'hub', 'tickets');
        $invoices = app(InvoiceService::class);

        return view('livewire.portal.order-show', [
            'o' => $this->order,
            'invoiceUrl' => $invoices->available($this->order) ? SignedUrl::temporarySignedRoute('invoice.download', now()->addMinutes(30), ['order' => $this->order->id]) : null,
            'canPay' => $this->canPay(),
            'instruments' => config('shared.payments.testCards'),
        ]);
    }
}
