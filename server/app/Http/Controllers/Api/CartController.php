<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Services\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(private PricingService $pricing) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request);
    }

    public function setItem(Request $request): JsonResponse
    {
        $data = $request->validate(['variant_id' => 'required|integer|exists:product_variants,id', 'quantity' => 'required|integer|min:0|max:1000']);
        $cart = Cart::forUser($request->user());
        if ($data['quantity'] === 0) {
            $cart->items()->where('product_variant_id', $data['variant_id'])->delete();
        } else {
            abort_unless(ProductVariant::where('id', $data['variant_id'])->where('is_active', true)->exists(), 422, 'Product unavailable.');
            $cart->items()->updateOrCreate(['product_variant_id' => $data['variant_id']], ['quantity' => $data['quantity']]);
        }

        return $this->respond($request);
    }

    public function removeItem(Request $request, int $variant): JsonResponse
    {
        Cart::forUser($request->user())->items()->where('product_variant_id', $variant)->delete();

        return $this->respond($request);
    }

    public function clear(Request $request): JsonResponse
    {
        $cart = Cart::forUser($request->user());
        $cart->items()->delete();
        $cart->update(['coupon_code' => null]);

        return $this->respond($request);
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|string|max:40']);
        $cart = Cart::forUser($request->user());
        $items = $cart->items()->pluck('quantity', 'product_variant_id')->all();
        $quote = $this->pricing->quote($request->user(), $items, $data['code']);
        if (! $quote['coupon']['applied']) {
            throw ValidationException::withMessages(['code' => $quote['coupon']['message'] ?? 'Invalid coupon.']);
        }
        $cart->update(['coupon_code' => $quote['coupon']['code']]);

        return $this->respond($request);
    }

    public function removeCoupon(Request $request): JsonResponse
    {
        Cart::forUser($request->user())->update(['coupon_code' => null]);

        return $this->respond($request);
    }

    private function respond(Request $request): JsonResponse
    {
        $cart = Cart::forUser($request->user())->fresh();
        $items = $cart->items()->pluck('quantity', 'product_variant_id')->all();
        $quote = $this->pricing->quote($request->user(), $items, $cart->coupon_code);
        // A coupon that no longer qualifies is dropped, but the message is surfaced.
        if ($cart->coupon_code && ! $quote['coupon']['applied']) {
            $cart->update(['coupon_code' => null]);
        }

        return response()->json(['data' => Presenter::quote($quote)]);
    }
}
