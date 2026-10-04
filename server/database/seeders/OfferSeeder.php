<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\DiscountSlab;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OfferSeeder extends Seeder
{
    public function run(): void
    {
        // Volume slabs come from shared/config.pkl so server & clients stay aligned.
        DiscountSlab::query()->delete();
        foreach (config('shared.pricingSlabs') as $s) {
            DiscountSlab::create(['min_subtotal' => $s['minSubtotal'], 'percent' => $s['percent']]);
        }

        Coupon::query()->delete();
        $coupons = [
            ['WELCOME500', 'Welcome gift', '₹500 off your first order of ₹5,000+', 'flat', 500, null, 5000, null, 1, true, true],
            ['FESTIVE5', 'Festive 5%', '5% off on orders above ₹8,000 (up to ₹2,000)', 'percent', 5, 2000, 8000, 500, 2, true, false],
            ['SNACKUP10', 'Snack-up 10%', '10% off, max ₹3,000, on orders above ₹20,000', 'percent', 10, 3000, 20000, 200, 1, true, false],
            ['KIRANA200', 'Kirana partner', 'Private code shared by our sales team: ₹200 off', 'flat', 200, null, 2500, null, 3, false, false],
        ];
        foreach ($coupons as [$code, $title, $desc, $type, $value, $max, $min, $limit, $perUser, $public, $first]) {
            Coupon::create([
                'code' => $code, 'title' => $title, 'description' => $desc, 'type' => $type, 'value' => $value,
                'max_discount' => $max, 'min_order' => $min, 'usage_limit' => $limit, 'per_user_limit' => $perUser,
                'is_public' => $public, 'first_order_only' => $first, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(3),
            ]);
        }

        Deal::query()->delete();
        $chips = Category::where('slug', 'chips-crisps')->first();
        $bhujia = Product::where('slug', 'aloo-bhujia')->first();
        $glucose = Product::where('slug', 'glucose-biscuits')->first();
        $noodles = Product::where('slug', 'masala-instant-noodles')->first();
        $mango = Product::where('slug', 'mango-fruit-drink')->first();
        Deal::create(['title' => 'Chips carnival', 'description' => '3% off all chips when you take 5+ cases', 'type' => 'percent', 'value' => 3, 'min_qty' => 5, 'category_id' => $chips->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2)]);
        Deal::create(['title' => 'Bhujia: buy 10 get 1 free', 'description' => 'Every 10 cases of Aloo Bhujia, 1 case free', 'type' => 'bxgy', 'buy_qty' => 10, 'free_qty' => 1, 'min_qty' => 10, 'product_id' => $bhujia->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2)]);
        Deal::create(['title' => 'Glucose flat ₹30 off', 'description' => '₹30 off per case on Glucose Biscuits', 'type' => 'flat', 'value' => 30, 'min_qty' => 3, 'product_id' => $glucose->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2)]);
        Deal::create(['title' => 'Noodles: buy 6 get 1 free', 'description' => 'Every 6 cases, 1 case free', 'type' => 'bxgy', 'buy_qty' => 6, 'free_qty' => 1, 'min_qty' => 6, 'product_id' => $noodles->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2)]);
        Deal::create(['title' => 'Mango drink 8% off', 'description' => '8% off Mango Fruit Drink, 4+ cases', 'type' => 'percent', 'value' => 8, 'min_qty' => 4, 'product_id' => $mango->id, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonths(2)]);
    }
}
