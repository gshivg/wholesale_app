<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Deal;
use App\Models\DiscountSlab;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Scenario;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase, Scenario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
    }

    public function test_gst_is_split_into_cgst_and_sgst_and_rounded(): void
    {
        $user = $this->shopUser();
        $v = $this->variant(price: 1000, gst: 12);
        $q = app(PricingService::class)->quote($user, [$v->id => 5]);

        $t = $q['totals'];
        $this->assertSame(500000, $t['gross']);
        $this->assertSame(500000, $t['taxable']);
        $this->assertSame(60000, $t['tax']);
        $this->assertSame(30000, $t['cgst']);
        $this->assertSame(30000, $t['sgst']);
        $this->assertSame(0, $t['igst']);
        $this->assertSame(560000, $t['total']);
        $this->assertEmpty($q['errors']);
    }

    public function test_interstate_shop_gets_igst(): void
    {
        $user = $this->shopUser(stateCode: '07');
        $v = $this->variant();
        $t = app(PricingService::class)->quote($user, [$v->id => 5])['totals'];

        $this->assertSame(60000, $t['igst']);
        $this->assertSame(0, $t['cgst']);
    }

    public function test_minimum_order_value_and_cases_are_enforced(): void
    {
        $user = $this->shopUser();
        $v = $this->variant(price: 100);
        $codes = array_column(app(PricingService::class)->quote($user, [$v->id => 2])['errors'], 'code');

        $this->assertContains('min_value', $codes);
        $this->assertContains('min_cases', $codes);
    }

    public function test_per_product_minimum_quantity(): void
    {
        $user = $this->shopUser();
        $v = $this->variant(price: 5000, min: 3);
        $codes = array_column(app(PricingService::class)->quote($user, [$v->id => 2])['errors'], 'code');

        $this->assertContains('min_qty', $codes);
    }

    public function test_stock_limit_blocks_checkout(): void
    {
        $user = $this->shopUser();
        $v = $this->variant(price: 1000, stock: 4);
        $q = app(PricingService::class)->quote($user, [$v->id => 6]);

        $this->assertContains('stock', array_column($q['errors'], 'code'));
        $this->assertFalse($q['can_checkout']);
    }

    public function test_unserviceable_shop_without_hub_cannot_order(): void
    {
        $user = $this->shopUser();
        $user->shop->update(['hub_id' => null]);
        $v = $this->variant();

        $this->assertContains('no_hub', array_column(app(PricingService::class)->quote($user->fresh('shop'), [$v->id => 5])['errors'], 'code'));
    }

    public function test_volume_slab_applies_before_tax(): void
    {
        DiscountSlab::create(['min_subtotal' => 10000, 'percent' => 5]);
        $user = $this->shopUser();
        $v = $this->variant(price: 2500, gst: 18);
        $t = app(PricingService::class)->quote($user, [$v->id => 5])['totals']; // ₹12,500

        $this->assertSame(1250000, $t['subtotal']);
        $this->assertSame(62500, $t['slab_discount']);
        $this->assertSame(1187500, $t['taxable']);
        $this->assertSame(213750, $t['tax']);          // 18% of 11,875
        $this->assertSame(1401300, $t['total']);       // ₹14,012.50 rounds half-up to ₹14,013
        $this->assertSame(50, $t['round_off']);
    }

    public function test_bxgy_deal_adds_free_cases_and_percent_deal_discounts(): void
    {
        $user = $this->shopUser();
        $bhujia = $this->variant('Bhujia', 1000, 12);
        $mango = $this->variant('Mango', 500, 12);
        Deal::create(['title' => 'Buy 5 get 1', 'type' => 'bxgy', 'buy_qty' => 5, 'free_qty' => 1, 'min_qty' => 5, 'product_id' => $bhujia->product_id]);
        Deal::create(['title' => '10% off', 'type' => 'percent', 'value' => 10, 'min_qty' => 2, 'product_id' => $mango->product_id]);

        $q = app(PricingService::class)->quote($user, [$bhujia->id => 10, $mango->id => 4]);
        $lines = collect($q['lines'])->keyBy('variant_id');

        $this->assertSame(2, $lines[$bhujia->id]['free_qty']);
        $this->assertSame(0, $lines[$bhujia->id]['deal']);
        $this->assertSame(20000, $lines[$mango->id]['deal']);
        $this->assertSame(2, $q['totals']['free_cases']);
    }

    public function test_coupon_percent_is_capped_and_validated(): void
    {
        $user = $this->shopUser();
        $v = $this->variant(price: 4000);
        Coupon::create(['code' => 'BIG10', 'title' => 'Ten', 'type' => 'percent', 'value' => 10, 'max_discount' => 500, 'min_order' => 5000]);

        $q = app(PricingService::class)->quote($user, [$v->id => 5], 'big10'); // ₹20,000, 10% = 2,000 → cap 500
        $this->assertTrue($q['coupon']['applied']);
        $this->assertSame(50000, $q['totals']['coupon_discount']);
        $this->assertSame(1950000, $q['totals']['taxable']);

        $bad = app(PricingService::class)->quote($user, [$v->id => 5], 'NOPE');
        $this->assertFalse($bad['coupon']['applied']);
    }

    public function test_discount_allocation_never_loses_paise(): void
    {
        $alloc = app(PricingService::class)->allocate(1001, [1 => 333, 2 => 333, 3 => 334]);
        $this->assertSame(1001, array_sum($alloc));
    }
}
