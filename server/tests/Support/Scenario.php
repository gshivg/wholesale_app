<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\Category;
use App\Models\Hub;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\User;
use App\Services\StockService;

/** Small, deterministic world for tests (independent of the demo seeders). */
trait Scenario
{
    protected Hub $hub;
    protected User $admin;

    protected function world(): void
    {
        $this->hub = Hub::create(['code' => 'PRG', 'name' => 'Prayagraj Main', 'type' => 'main', 'city' => 'Prayagraj', 'district' => 'Prayagraj']);
        $this->admin = $this->staff(Role::SuperAdmin, '9000000001');
    }

    protected function staff(Role $role, string $phone, ?Hub $hub = null): User
    {
        return User::create(['name' => $role->label(), 'phone' => $phone, 'password' => 'password', 'role' => $role,
            'hub_id' => $hub?->id, 'phone_verified_at' => now()]);
    }

    protected function shopUser(string $phone = '9111111111', string $status = 'approved', string $pin = '211001', string $stateCode = '09'): User
    {
        $u = User::create(['name' => 'Owner ' . $phone, 'phone' => $phone, 'email' => "$phone@example.com", 'password' => 'password',
            'role' => Role::Customer, 'phone_verified_at' => now()]);
        Shop::create(['user_id' => $u->id, 'hub_id' => $this->hub->id, 'shop_name' => 'Test Shop', 'shop_type' => 'Kirana / General store',
            'pan' => 'ABCDE1234F', 'address_line1' => '1 Main Rd', 'city' => 'Prayagraj', 'district' => 'Prayagraj', 'pincode' => $pin,
            'state_code' => $stateCode, 'status' => $status, 'credit_limit' => 10000]);

        return $u->fresh('shop');
    }

    /** @return ProductVariant a ₹1,000/case variant with 12% GST and ample stock */
    protected function variant(string $name = 'Chips', float $price = 1000, float $gst = 12, int $stock = 100, int $min = 1): ProductVariant
    {
        $cat = Category::firstOrCreate(['slug' => 'snacks'], ['name' => 'Snacks']);
        $p = Product::create(['category_id' => $cat->id, 'name' => $name, 'slug' => str($name)->slug() . '-' . uniqid(), 'gst_rate' => $gst]);
        $v = ProductVariant::create(['product_id' => $p->id, 'sku' => strtoupper(uniqid('SKU')), 'label' => '10 packs', 'packs_per_case' => 10,
            'price' => $price, 'mrp' => $price * 1.2, 'min_qty' => $min]);
        app(StockService::class)->adjust($this->hub->id, $v->id, $stock);

        return $v;
    }
}
