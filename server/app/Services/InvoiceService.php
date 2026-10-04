<?php

namespace App\Services;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceService
{
    public function number(Order $order): string
    {
        return config('wholesale.invoice_prefix') . '/' . $order->number;
    }

    public function pdf(Order $order)
    {
        $order->load(['items', 'shop.user', 'hub']);

        return Pdf::loadView('invoices.invoice', [
            'order' => $order,
            'invoiceNo' => $this->number($order),
            'company' => config('shared.company'),
            'brand' => config('shared.brand'),
        ])->setPaper('a4');
    }

    /** Invoices exist from confirmation onwards (not for cancelled-before-confirm orders). */
    public function available(Order $order): bool
    {
        return $order->confirmed_at !== null && $order->status !== 'cancelled';
    }
}
