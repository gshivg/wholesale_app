<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

use App\Models\Order;
use App\Services\InvoiceService;

// Signed, short-lived link handed out by the API / portal so the invoice opens in any browser.
Route::get('/invoices/{order}', function (Order $order, InvoiceService $invoices) {
    abort_unless($invoices->available($order), 404);

    return $invoices->pdf($order)->stream("invoice-{$order->number}.pdf");
})->name('invoice.download')->middleware('signed');
