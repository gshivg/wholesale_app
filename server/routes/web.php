<?php

use App\Livewire;
use App\Models\Order;
use App\Services\InvoiceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', Livewire\Landing::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', Livewire\Auth\Login::class)->name('login');
    Route::get('/register', Livewire\Auth\Register::class)->name('register');
    Route::get('/forgot-password', Livewire\Auth\ForgotPassword::class)->name('password.forgot');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect('/');
})->name('logout');

Route::get('/notifications', Livewire\Notifications::class)->middleware('auth')->name('notifications');

// ── Shopkeeper portal (same features as the mobile app) ─────────────────────────
Route::middleware(['auth', 'role:customer'])->prefix('portal')->group(function () {
    Route::get('/', Livewire\Portal\Dashboard::class)->name('portal');
    Route::get('/catalog', Livewire\Portal\Catalog::class)->name('portal.catalog');
    Route::get('/offers', Livewire\Portal\Offers::class)->name('portal.offers');
    Route::get('/cart', Livewire\Portal\Cart::class)->name('portal.cart');
    Route::get('/orders', Livewire\Portal\Orders::class)->name('portal.orders');
    Route::get('/orders/{order}', Livewire\Portal\OrderShow::class)->name('portal.orders.show');
    Route::get('/support', Livewire\Portal\Tickets::class)->name('portal.tickets');
    Route::get('/support/{ticket}', Livewire\Portal\TicketShow::class)->name('portal.tickets.show');
    Route::get('/profile', Livewire\Portal\Profile::class)->name('portal.profile');
});

// ── Staff dashboards ────────────────────────────────────────────────────────────
Route::middleware('auth')->prefix('staff')->group(function () {
    Route::get('/', fn () => redirect(auth()->user()->role->home()));

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin', Livewire\Staff\Overview::class)->name('staff.admin');
        Route::get('/products', Livewire\Staff\Products::class)->name('staff.products');
        Route::get('/inventory', Livewire\Staff\Inventory::class)->name('staff.inventory');
        Route::get('/offers', Livewire\Staff\Offers::class)->name('staff.offers');
        Route::get('/team', Livewire\Staff\Team::class)->name('staff.team');
        Route::get('/audit', Livewire\Staff\Audit::class)->name('staff.audit');
    });
    Route::middleware('role:order_manager')->group(function () {
        Route::get('/orders', Livewire\Staff\Orders::class)->name('staff.orders');
        Route::get('/orders/{order}', Livewire\Staff\OrderShow::class)->name('staff.orders.show');
        Route::get('/shops', Livewire\Staff\Shops::class)->name('staff.shops');
        Route::get('/shops/{shop}', Livewire\Staff\ShopShow::class)->name('staff.shops.show');
    });
    Route::get('/packaging', Livewire\Staff\OrderQueue::class)->defaults('mode', 'packaging')->middleware('role:packaging')->name('staff.packaging');
    Route::get('/warehouse', Livewire\Staff\OrderQueue::class)->defaults('mode', 'warehouse')->middleware('role:warehouse')->name('staff.warehouse');
    Route::get('/delivery', Livewire\Staff\OrderQueue::class)->defaults('mode', 'delivery')->middleware('role:delivery')->name('staff.delivery');
    Route::middleware('role:support')->group(function () {
        Route::get('/support', Livewire\Staff\Support\Queue::class)->name('staff.support');
        Route::get('/support/{ticket}', Livewire\Staff\Support\TicketShow::class)->name('staff.support.show');
    });
});

// Signed, short-lived link handed out by the API / portal so the invoice opens in any browser.
Route::get('/invoices/{order}', function (Order $order, InvoiceService $invoices) {
    abort_unless($invoices->available($order), 404);

    return $invoices->pdf($order)->stream("invoice-{$order->number}.pdf");
})->name('invoice.download')->middleware('signed');
