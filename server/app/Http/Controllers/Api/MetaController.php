<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Support\Presenter;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\DiscountSlab;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    /** Public: runtime view of the pkl config (so the server stays authoritative). */
    public function meta(): JsonResponse
    {
        $c = config('shared');

        return response()->json([
            'brand' => $c['brand'],
            'locales' => collect($c['locales'])->where('enabled', true)->values(),
            'shop_types' => $c['shopTypes'],
            'service_areas' => $c['serviceAreas'],
            'orders' => $c['orders'],
            'support' => $c['support'],
            'payments' => ['currency' => $c['payments']['currency'], 'symbol' => $c['payments']['currencySymbol'], 'gateway' => $c['payments']['gateway']],
            'otp' => ['length' => $c['otp']['length'], 'prototype' => (bool) config('wholesale.otp_prototype')],
        ]);
    }

    public function offers(): JsonResponse
    {
        return response()->json([
            'slabs' => DiscountSlab::where('is_active', true)->orderBy('min_subtotal')->get()->map(fn ($s) => [
                'min_subtotal' => (float) $s->min_subtotal, 'percent' => (float) $s->percent,
            ]),
            'deals' => Deal::live()->get()->map(fn ($d) => Presenter::deal($d)),
            'coupons' => Coupon::live()->where('is_public', true)->get()->map(fn ($c) => [
                'code' => $c->code, 'title' => $c->title, 'description' => $c->description, 'summary' => $c->summary(),
                'min_order' => (float) $c->min_order, 'ends_at' => $c->ends_at?->toIso8601String(),
            ]),
        ]);
    }
}
