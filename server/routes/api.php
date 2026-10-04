<?php

use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('meta', [Api\MetaController::class, 'meta']);

    Route::prefix('auth')->middleware('throttle:20,1')->group(function () {
        Route::post('register', [Api\AuthController::class, 'register']);
        Route::post('otp/send', [Api\AuthController::class, 'sendOtp']);
        Route::post('otp/verify', [Api\AuthController::class, 'verifyOtp']);
        Route::post('login', [Api\AuthController::class, 'login']);
        Route::post('password/reset', [Api\AuthController::class, 'resetPassword']);
    });

    Route::middleware(['auth:sanctum', 'role:customer'])->group(function () {
        Route::post('auth/logout', [Api\AuthController::class, 'logout']);
        Route::get('me', [Api\ProfileController::class, 'show']);
        Route::put('me', [Api\ProfileController::class, 'update']);
        Route::post('me/password', [Api\ProfileController::class, 'changePassword']);

        Route::get('categories', [Api\CatalogController::class, 'categories']);
        Route::get('products', [Api\CatalogController::class, 'index']);
        Route::get('products/{product}', [Api\CatalogController::class, 'show']);
        Route::get('offers', [Api\MetaController::class, 'offers']);

        Route::get('notifications', [Api\NotificationController::class, 'index']);
        Route::post('notifications/read', [Api\NotificationController::class, 'markRead']);

        Route::get('tickets', [Api\TicketController::class, 'index']);
        Route::post('tickets', [Api\TicketController::class, 'store']);
        Route::get('tickets/{ticket}', [Api\TicketController::class, 'show']);
        Route::post('tickets/{ticket}/messages', [Api\TicketController::class, 'reply']);
        Route::post('tickets/{ticket}/close', [Api\TicketController::class, 'close']);
        Route::post('tickets/{ticket}/rating', [Api\TicketController::class, 'rate']);

        Route::get('orders', [Api\OrderController::class, 'index']);
        Route::get('orders/{order}', [Api\OrderController::class, 'show']);
        Route::get('orders/{order}/invoice-link', [Api\OrderController::class, 'invoiceLink']);

        // Ordering requires an approved shop.
        Route::middleware('approved')->group(function () {
            Route::get('cart', [Api\CartController::class, 'show']);
            Route::put('cart/items', [Api\CartController::class, 'setItem']);
            Route::delete('cart/items/{variant}', [Api\CartController::class, 'removeItem']);
            Route::delete('cart', [Api\CartController::class, 'clear']);
            Route::post('cart/coupon', [Api\CartController::class, 'applyCoupon']);
            Route::delete('cart/coupon', [Api\CartController::class, 'removeCoupon']);

            Route::post('orders', [Api\OrderController::class, 'store']);
            Route::post('orders/{order}/cancel', [Api\OrderController::class, 'cancel']);
            Route::post('orders/{order}/reorder', [Api\OrderController::class, 'reorder']);
            Route::post('orders/{order}/payments', [Api\OrderController::class, 'pay']);
            Route::post('payments/{payment}/confirm', [Api\OrderController::class, 'confirmPayment']);
        });
    });
});
