<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Sandbox payment gateway. It mimics the create-intent → confirm flow of a real gateway
 * (e.g. Razorpay) so the app and web only depend on this service's two methods.
 * Test instruments are listed in shared/config.pkl (payments.testCards).
 */
class PaymentService
{
    public function __construct(private OrderService $orders) {}

    public function createIntent(Order $order): Payment
    {
        if ($order->payment_status === 'paid') {
            throw new InvalidArgumentException('Order is already paid.');
        }
        if (! in_array($order->status, ['placed', 'confirmed'], true)) {
            throw new InvalidArgumentException('This order can no longer be paid online.');
        }

        return Payment::create([
            'order_id' => $order->id,
            'gateway' => config('shared.payments.gateway'),
            'reference' => 'sbx_' . Str::lower(Str::random(16)),
            'amount' => $order->total,
            'currency' => config('shared.payments.currency'),
            'status' => 'created',
        ]);
    }

    /** @param string $instrument card number or UPI id */
    public function confirm(Payment $payment, string $method, string $instrument): Payment
    {
        if ($payment->status !== 'created') {
            throw new InvalidArgumentException('This payment is already ' . $payment->status . '.');
        }
        $instrument = preg_replace('/\s+/', '', $instrument);
        $outcome = config('shared.payments.testCards')[$instrument] ?? null;
        if (! $outcome) {
            throw new InvalidArgumentException('Unknown sandbox instrument. Use a test card or test UPI id.');
        }

        $ok = $outcome === 'success';
        $payment->update([
            'status' => $ok ? 'paid' : 'failed',
            'method' => $method,
            'gateway_payment_id' => 'pay_' . Str::lower(Str::random(14)),
            'payload' => ['instrument_tail' => substr($instrument, -4), 'outcome' => $outcome],
        ]);

        $order = $payment->order;
        if ($ok) {
            $order->update(['payment_status' => 'paid']);
            $this->orders->log($order, $order->status, null, 'Online payment received (sandbox)');
            $order->user->notify(new \App\Notifications\AppNotice('Payment received', "Payment for {$order->number} was successful.", 'order', ['order_id' => $order->id]));
        } else {
            $order->update(['payment_status' => 'failed']);
            $order->user->notify(new \App\Notifications\AppNotice('Payment failed', "Payment for {$order->number} failed. You can retry from the order page.", 'order', ['order_id' => $order->id]));
        }

        return $payment->fresh();
    }
}
