<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Hub;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

/** Demo shopkeepers (password "password") plus a spread of orders across every stage and a few tickets. */
class DemoShopSeeder extends Seeder
{
    public function run(): void
    {
        $hubs = Hub::pluck('id', 'code');
        $shops = [
            ['Ramesh Gupta', '9100000001', 'ramesh@example.com', 'Gupta General Store', 'Kirana / General store', '09ABCDE1234F1Z5', 'ABCDE1234F', '12 Civil Lines', 'Prayagraj', '211001', 'approved', 50000],
            ['Sanjay Verma', '9100000002', 'sanjay@example.com', 'Verma Super Mart', 'Supermarket', '09FGHIJ5678K1Z2', 'FGHIJ5678K', 'Hazratganj Market', 'Lucknow', '226001', 'approved', 100000],
            ['Anita Singh', '9100000003', null, 'Singh Sweets & Snacks', 'Sweet shop / Bakery', null, 'KLMNO9012P', 'Godowlia Chowk', 'Varanasi', '221001', 'approved', 25000],
            ['Mohit Tiwari', '9100000004', 'mohit@example.com', 'Tiwari Convenience', 'Convenience store', null, 'PQRST3456U', 'Swaroop Nagar', 'Kanpur', '208002', 'pending', 0],
        ];
        $users = [];
        foreach ($shops as [$owner, $phone, $email, $shop, $type, $gstin, $pan, $addr, $city, $pin, $status, $credit]) {
            $u = User::updateOrCreate(['phone' => $phone], [
                'name' => $owner, 'email' => $email, 'password' => 'password', 'phone_verified_at' => now(), 'role' => 'customer',
            ]);
            Shop::updateOrCreate(['user_id' => $u->id], [
                'hub_id' => $hubs[config('shared.serviceAreas')[array_search($city, array_column(config('shared.serviceAreas'), 'district'))]['hubCode']],
                'shop_name' => $shop, 'shop_type' => $type, 'gstin' => $gstin, 'pan' => $pan, 'address_line1' => $addr,
                'city' => $city, 'district' => $city, 'pincode' => $pin, 'status' => $status,
                'credit_limit' => $credit, 'approved_at' => $status === 'approved' ? now() : null,
                'approved_by' => $status === 'approved' ? User::where('phone', '9000000001')->value('id') : null,
            ]);
            $users[$phone] = $u->fresh('shop');
        }

        $orders = app(OrderService::class);
        $variants = ProductVariant::with('product')->get()->keyBy('sku');
        $pick = fn (array $skuQty) => collect($skuQty)->mapWithKeys(fn ($q, $needle) => [
            $variants->first(fn ($v) => str_contains($v->sku, $needle))->id => $q,
        ])->all();

        $manager = User::where('phone', '9000000002')->first();
        $admin = User::where('phone', '9000000001')->first();
        $staff = fn (string $prefix, string $hub) => User::where('hub_id', $hubs[$hub])->where('role', $prefix)->first();

        $scenarios = [
            // [shop phone, items, payment, final stage, hub]
            ['9100000001', ['CLASSIC-SALTED-CHIPS-60' => 6, 'ALOO-BHUJIA-48' => 10, 'GLUCOSE-BISCUITS-60' => 4], 'cod', 'delivered', 'PRG'],
            ['9100000001', ['MASALA-INSTANT-NOODLES' => 7, 'MANGO-FRUIT-DRINK-24' => 5], 'credit', 'out_for_delivery', 'PRG'],
            ['9100000002', ['BUTTER-COOKIES' => 6, 'MILK-CHOCO-BAR' => 3, 'ROASTED-MAKHANA' => 4], 'online', 'packed', 'LKO'],
            ['9100000002', ['CLASSIC-SALTED-CHIPS-60' => 5, 'CREAM-ONION' => 4], 'cod', 'confirmed', 'LKO'],
            ['9100000003', ['ALOO-BHUJIA-48' => 12, 'MOONG-DAL-48' => 4, 'PEANUT-MASALA' => 3], 'cod', 'placed', 'VNS'],
            ['9100000001', ['GLUCOSE-BISCUITS-60' => 6, 'MARIE-TEA' => 2], 'cod', 'dispatched', 'PRG'],
        ];
        foreach ($scenarios as [$phone, $items, $pay, $final, $hubCode]) {
            $user = $users[$phone];
            $cart = Cart::forUser($user);
            $cart->items()->delete();
            foreach ($pick($items) as $vid => $qty) {
                $cart->items()->create(['product_variant_id' => $vid, 'quantity' => $qty]);
            }
            $order = $orders->place($user, $pay, 'Please deliver before 11am.');
            if ($pay === 'online' && $final !== 'placed') {
                $order->update(['payment_status' => 'paid']);
            }
            $chain = [
                'confirmed' => [$manager, 'confirmed'],
                'packing' => [$staff('packaging', $hubCode), 'packing'],
                'packed' => [$staff('packaging', $hubCode), 'packed'],
                'dispatched' => [$staff('warehouse', $hubCode), 'dispatched'],
                'out_for_delivery' => [null, 'out_for_delivery'],
                'delivered' => [null, 'delivered'],
            ];
            foreach ($chain as $stage => [$actor, $to]) {
                if (! $this->reached($final, $stage)) {
                    break;
                }
                $order = $order->fresh();
                $actor ??= $order->agent;
                $orders->transition($order, $to, $actor, ['otp' => $order->delivery_otp, 'note' => null]);
            }
        }

        $tickets = app(TicketService::class);
        $o = $users['9100000001']->orders()->where('status', 'delivered')->first();
        $t = $tickets->open($users['9100000001'], ['category' => 'damaged', 'order_id' => $o?->id, 'subject' => 'Two cartons of chips arrived crushed', 'message' => 'Two cartons from my last delivery were damaged in transit. Photos can be shared if needed.']);
        $tickets->reply($t, $t->agent ?? $admin, 'Sorry about that! We have logged it for a credit note. Could you confirm how many packs were affected?');
        $tickets->open($users['9100000002'], ['category' => 'delay', 'subject' => 'When will my order be delivered?', 'message' => 'The order is showing as packed since yesterday.']);
    }

    private function reached(string $final, string $stage): bool
    {
        $p = ['placed', 'confirmed', 'packing', 'packed', 'dispatched', 'out_for_delivery', 'delivered'];

        return array_search($stage, $p) <= array_search($final, $p);
    }
}
