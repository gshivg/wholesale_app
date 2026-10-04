<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\HubStock;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Scenario;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase, Scenario;

    private $shop;
    private $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
        $this->shop = $this->shopUser();
        $this->variant = $this->variant(price: 1000, stock: 50);
    }

    private function place(string $pay = 'cod', int $qty = 6, ?string $coupon = null)
    {
        $cart = Cart::forUser($this->shop);
        $cart->items()->delete();
        $cart->items()->create(['product_variant_id' => $this->variant->id, 'quantity' => $qty]);
        $cart->update(['coupon_code' => $coupon]);

        return app(OrderService::class)->place($this->shop, $pay);
    }

    private function stock(): HubStock
    {
        return HubStock::where('product_variant_id', $this->variant->id)->first();
    }

    public function test_placing_reserves_stock_and_clears_cart(): void
    {
        $order = $this->place();

        $this->assertSame('placed', $order->status);
        $this->assertSame(6, $this->stock()->reserved);
        $this->assertSame(50, $this->stock()->quantity);
        $this->assertSame(0, Cart::forUser($this->shop)->items()->count());
        $this->assertCount(1, $order->events);
        $this->assertSame(4, strlen($order->delivery_otp));
    }

    public function test_pending_shop_cannot_place_orders(): void
    {
        $this->shop->shop->update(['status' => 'pending']);
        $this->expectException(HttpException::class);
        $this->place();
    }

    public function test_full_lifecycle_with_role_checks_and_delivery_otp(): void
    {
        $svc = app(OrderService::class);
        $manager = $this->staff(Role::OrderManager, '9000000002');
        $packer = $this->staff(Role::Packaging, '9000000003', $this->hub);
        $wh = $this->staff(Role::Warehouse, '9000000004', $this->hub);
        $driver = $this->staff(Role::Delivery, '9000000005', $this->hub);
        $order = $this->place();

        // packer cannot confirm; stages cannot be skipped
        try {
            $svc->transition($order, 'confirmed', $packer);
            $this->fail('packer confirmed an order');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $order = $svc->transition($order, 'confirmed', $manager);
        try {
            $svc->transition($order, 'packed', $packer);
            $this->fail('stage was skipped');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $order = $svc->transition($order, 'packing', $packer);
        $order = $svc->transition($order, 'packed', $packer);
        $order = $svc->transition($order, 'dispatched', $wh);

        $this->assertSame($driver->id, $order->delivery_agent_id);
        $this->assertSame(44, $this->stock()->quantity);
        $this->assertSame(0, $this->stock()->reserved);

        $order = $svc->transition($order, 'out_for_delivery', $driver);
        try {
            $svc->transition($order, 'delivered', $driver, ['otp' => '0000']);
            $this->fail('wrong OTP accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('otp', $e->errors());
        }
        $order = $svc->transition($order, 'delivered', $driver, ['otp' => $order->delivery_otp]);

        $this->assertSame('delivered', $order->status);
        $this->assertSame('paid', $order->payment_status); // COD collected
        $this->assertNotNull($order->delivered_at);
        $this->assertCount(7, $order->events);
    }

    public function test_hub_staff_cannot_touch_other_hubs(): void
    {
        $other = \App\Models\Hub::create(['code' => 'LKO', 'name' => 'Lucknow', 'type' => 'dc', 'city' => 'Lucknow', 'district' => 'Lucknow']);
        $manager = $this->staff(Role::OrderManager, '9000000002');
        $outsider = $this->staff(Role::Packaging, '9000000009', $other);
        $svc = app(OrderService::class);
        $order = $svc->transition($this->place(), 'confirmed', $manager);

        $this->expectException(HttpException::class);
        $svc->transition($order, 'packing', $outsider);
    }

    public function test_customer_can_cancel_only_before_packing_and_stock_is_released(): void
    {
        $svc = app(OrderService::class);
        $order = $this->place();
        $order = $svc->transition($order, 'cancelled', $this->shop, ['note' => 'Changed mind']);

        $this->assertSame('cancelled', $order->status);
        $this->assertSame(0, $this->stock()->reserved);

        $second = $this->place();
        $packer = $this->staff(Role::Packaging, '9000000003', $this->hub);
        $svc->transition($second, 'confirmed', $this->staff(Role::OrderManager, '9000000002'));
        $svc->transition($second->fresh(), 'packing', $packer);
        $this->expectException(HttpException::class);
        $svc->transition($second->fresh(), 'cancelled', $this->shop);
    }

    public function test_online_orders_need_payment_before_confirmation(): void
    {
        $manager = $this->staff(Role::OrderManager, '9000000002');
        $order = $this->place('online');

        $this->expectException(ValidationException::class);
        app(OrderService::class)->transition($order, 'confirmed', $manager);
    }

    public function test_credit_orders_use_and_release_credit(): void
    {
        $order = $this->place('credit', 5); // 5,000 + 12% = 5,600
        $this->assertEquals(5600, $this->shop->shop->fresh()->credit_used);

        app(OrderService::class)->transition($order, 'cancelled', $this->shop);
        $this->assertEquals(0, $this->shop->shop->fresh()->credit_used);

        $this->expectException(ValidationException::class);
        $this->place('credit', 10); // 11,200 > 10,000 limit
    }

    public function test_coupon_is_redeemed_once_per_user_and_released_on_cancel(): void
    {
        $c = Coupon::create(['code' => 'ONCE', 'title' => 'Once', 'type' => 'flat', 'value' => 300, 'min_order' => 1000, 'per_user_limit' => 1]);
        $order = $this->place('cod', 5, 'ONCE');

        $this->assertEquals(300, $order->coupon_discount);
        $this->assertSame(1, $c->fresh()->times_used);

        $again = app(\App\Services\PricingService::class)->quote($this->shop, [$this->variant->id => 5], 'ONCE');
        $this->assertFalse($again['coupon']['applied']);

        app(OrderService::class)->transition($order, 'cancelled', $this->shop);
        $this->assertSame(0, $c->fresh()->times_used);
    }

    public function test_concurrent_overselling_is_prevented(): void
    {
        $this->variant->stocks()->update(['quantity' => 8]);
        $this->place('cod', 6);

        $this->expectException(ValidationException::class);
        $this->place('cod', 6);
    }
}
