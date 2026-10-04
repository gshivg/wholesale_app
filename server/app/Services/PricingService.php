<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Deal;
use App\Models\DiscountSlab;
use App\Models\ProductVariant;
use App\Models\User;

/**
 * Turns {variant => cases} into a fully priced, GST-split quote.
 *
 * Order of operations (all amounts exclude GST until the end):
 *   1. line gross            = cases × case price
 *   2. best live deal / line  (percent, flat per case, or buy-X-get-Y-free cases)
 *   3. volume slab            = % of (subtotal after deals), if threshold met
 *   4. coupon                 = on (subtotal after deals & slab), capped by max_discount
 *   5. order-level discounts are allocated to lines pro-rata, then GST is computed per line
 *   6. CGST+SGST (same state) or IGST, rounded to the nearest rupee
 */
class PricingService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int,int>  $items  variant_id => cases
     */
    public function quote(User $user, array $items, ?string $couponCode = null): array
    {
        $shop = $user->shop;
        $errors = [];
        $variants = ProductVariant::with('product.category')
            ->whereIn('id', array_keys($items))->get()->keyBy('id');
        $deals = Deal::live()->get();

        $lines = [];
        foreach ($items as $variantId => $qty) {
            $v = $variants[$variantId] ?? null;
            if (! $v || ! $v->is_active || ! $v->product->is_active) {
                $errors[] = ['code' => 'unavailable', 'variant_id' => (int) $variantId, 'message' => 'A product in your cart is no longer available.'];
                continue;
            }
            $qty = (int) $qty;
            $unit = (int) round($v->price * 100);
            $gross = $unit * $qty;

            [$dealDiscount, $free, $dealTitle] = $this->bestDeal($deals, $v, $qty, $unit);

            $available = $shop?->hub_id ? $this->stock->available($shop->hub_id, $v->id) : 0;
            if ($qty < $v->min_qty) {
                $errors[] = ['code' => 'min_qty', 'variant_id' => $v->id, 'message' => "{$v->product->name} ({$v->label}): minimum {$v->min_qty} cases."];
            }
            if ($qty > $v->max_qty) {
                $errors[] = ['code' => 'max_qty', 'variant_id' => $v->id, 'message' => "{$v->product->name} ({$v->label}): maximum {$v->max_qty} cases per order."];
            }
            if ($qty + $free > $available) {
                $errors[] = ['code' => 'stock', 'variant_id' => $v->id, 'message' => "{$v->product->name} ({$v->label}): only {$available} cases in stock."];
            }

            $lines[$v->id] = [
                'variant' => $v,
                'variant_id' => $v->id,
                'product_id' => $v->product_id,
                'name' => $v->product->name,
                'label' => $v->label,
                'sku' => $v->sku,
                'hsn_code' => $v->product->hsn_code,
                'packs_per_case' => $v->packs_per_case,
                'qty' => $qty,
                'free_qty' => $free,
                'unit' => $unit,
                'mrp' => (int) round($v->mrp * 100),
                'gross' => $gross,
                'deal' => $dealDiscount,
                'deal_title' => $dealTitle,
                'net' => $gross - $dealDiscount,
                'gst_rate' => (float) $v->product->gst_rate,
                'min_qty' => $v->min_qty,
                'available' => $available,
            ];
        }

        $grossTotal = array_sum(array_column($lines, 'gross'));
        $dealTotal = array_sum(array_column($lines, 'deal'));
        $subtotal = $grossTotal - $dealTotal;
        $totalCases = array_sum(array_column($lines, 'qty'));

        // Volume slab
        $slab = DiscountSlab::where('is_active', true)->where('min_subtotal', '<=', $subtotal / 100)
            ->orderByDesc('min_subtotal')->first();
        $slabPercent = $slab ? (float) $slab->percent : 0.0;
        $slabTotal = (int) round($subtotal * $slabPercent / 100);
        $nextSlab = DiscountSlab::where('is_active', true)->where('min_subtotal', '>', $subtotal / 100)
            ->orderBy('min_subtotal')->first();
        $afterSlab = $subtotal - $slabTotal;

        // Coupon
        $coupon = ['code' => $couponCode, 'applied' => false, 'message' => null, 'discount' => 0];
        $couponTotal = 0;
        if ($couponCode) {
            [$c, $msg] = $this->validateCoupon($user, $couponCode, $afterSlab);
            if ($c) {
                $couponTotal = $c->type === 'percent'
                    ? (int) round($afterSlab * (float) $c->value / 100)
                    : (int) round((float) $c->value * 100);
                if ($c->max_discount) {
                    $couponTotal = min($couponTotal, (int) round((float) $c->max_discount * 100));
                }
                $couponTotal = min($couponTotal, $afterSlab);
                $coupon = ['code' => $c->code, 'applied' => true, 'message' => $c->title, 'discount' => $couponTotal, 'id' => $c->id];
            } else {
                $coupon['message'] = $msg;
            }
        }

        // Minimums
        $minValue = (int) config('shared.orders.minOrderValue');
        $minCases = (int) config('shared.orders.minTotalCases');
        if ($lines && $subtotal < $minValue * 100) {
            $errors[] = ['code' => 'min_value', 'message' => 'Minimum order value is ₹' . number_format($minValue) . ' (after deals).'];
        }
        if ($lines && $totalCases < $minCases) {
            $errors[] = ['code' => 'min_cases', 'message' => "Minimum order is {$minCases} cases in total."];
        }
        if ($user->isStaff() === false && ! $shop?->hub_id) {
            $errors[] = ['code' => 'no_hub', 'message' => 'Your PIN code is not in a serviceable area yet.'];
        }

        // Allocate order-level discounts pro-rata, then tax
        $weights = array_map(fn ($l) => $l['net'], $lines);
        $slabAlloc = $this->allocate($slabTotal, $weights);
        $afterSlabWeights = [];
        foreach ($lines as $id => $l) {
            $afterSlabWeights[$id] = $l['net'] - $slabAlloc[$id];
        }
        $couponAlloc = $this->allocate($couponTotal, $afterSlabWeights);

        $sameState = ! $shop || $shop->state_code === config('shared.company.stateCode');
        $taxable = $tax = 0;
        foreach ($lines as $id => &$l) {
            $l['slab'] = $slabAlloc[$id];
            $l['coupon'] = $couponAlloc[$id];
            $l['taxable'] = $l['net'] - $l['slab'] - $l['coupon'];
            $l['tax'] = (int) round($l['taxable'] * $l['gst_rate'] / 100);
            $l['total'] = $l['taxable'] + $l['tax'];
            $taxable += $l['taxable'];
            $tax += $l['tax'];
        }
        unset($l);

        $grand = $taxable + $tax;
        $rounded = (int) (round($grand / 100) * 100);
        $cgst = $sameState ? intdiv($tax, 2) : 0;
        $sgst = $sameState ? $tax - $cgst : 0;
        $igst = $sameState ? 0 : $tax;

        return [
            'lines' => array_values($lines),
            'totals' => [
                'gross' => $grossTotal,
                'deal_discount' => $dealTotal,
                'subtotal' => $subtotal,
                'slab_percent' => $slabPercent,
                'slab_discount' => $slabTotal,
                'coupon_discount' => $couponTotal,
                'taxable' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'tax' => $tax,
                'round_off' => $rounded - $grand,
                'total' => $rounded,
                'total_cases' => $totalCases,
                'free_cases' => array_sum(array_column($lines, 'free_qty')),
                'savings' => $dealTotal + $slabTotal + $couponTotal,
            ],
            'coupon' => $coupon,
            'next_slab' => $nextSlab ? [
                'min_subtotal' => (float) $nextSlab->min_subtotal,
                'percent' => (float) $nextSlab->percent,
                'remaining' => max(0, (int) round((float) $nextSlab->min_subtotal * 100) - $subtotal),
            ] : null,
            'minimums' => ['order_value' => $minValue, 'total_cases' => $minCases],
            'errors' => $errors,
            'can_checkout' => $lines && ! $errors,
        ];
    }

    /** @return array{0:?Coupon,1:?string} */
    public function validateCoupon(User $user, string $code, int $subtotalPaise): array
    {
        $c = Coupon::live()->whereRaw('lower(code) = ?', [mb_strtolower(trim($code))])->first();
        if (! $c) {
            return [null, 'This coupon is invalid or has expired.'];
        }
        if ($c->usage_limit !== null && $c->times_used >= $c->usage_limit) {
            return [null, 'This coupon has reached its usage limit.'];
        }
        if ($c->redemptions()->where('user_id', $user->id)->count() >= $c->per_user_limit) {
            return [null, 'You have already used this coupon.'];
        }
        if ($c->first_order_only && $user->orders()->where('status', '!=', 'cancelled')->exists()) {
            return [null, 'This coupon is only valid on your first order.'];
        }
        if ($subtotalPaise < (float) $c->min_order * 100) {
            return [null, 'Add items worth ₹' . number_format((float) $c->min_order) . ' or more to use this coupon.'];
        }

        return [$c, null];
    }

    /** @return array{0:int,1:int,2:?string} discount paise, free cases, deal title */
    private function bestDeal($deals, ProductVariant $v, int $qty, int $unit): array
    {
        $best = [0, 0, null];
        $bestBenefit = 0;
        foreach ($deals as $d) {
            if (! $d->appliesTo($v) || $qty < max(1, $d->min_qty)) {
                continue;
            }
            $discount = 0;
            $free = 0;
            if ($d->type === 'percent') {
                $discount = (int) round($unit * $qty * (float) $d->value / 100);
            } elseif ($d->type === 'flat') {
                $discount = min($unit * $qty, (int) round((float) $d->value * 100) * $qty);
            } elseif ($d->type === 'bxgy' && $d->buy_qty > 0) {
                $free = intdiv($qty, $d->buy_qty) * (int) $d->free_qty;
            }
            $benefit = $discount + $free * $unit;
            if ($benefit > $bestBenefit) {
                $bestBenefit = $benefit;
                $best = [$discount, $free, $d->title];
            }
        }

        return $best;
    }

    /**
     * Split $total across keys proportionally to $weights with no lost paise.
     *
     * @param  array<int|string,int>  $weights
     * @return array<int|string,int>
     */
    public function allocate(int $total, array $weights): array
    {
        $out = array_fill_keys(array_keys($weights), 0);
        $sum = array_sum($weights);
        if ($total <= 0 || $sum <= 0) {
            return $out;
        }
        $given = 0;
        $keys = array_keys($weights);
        $last = end($keys);
        foreach ($weights as $k => $w) {
            $out[$k] = $k === $last ? $total - $given : (int) floor($total * $w / $sum);
            $given += $out[$k];
        }

        return $out;
    }
}
