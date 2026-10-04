<?php

namespace Tests\Feature;

use App\Livewire as Pages;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** Boots every page against the demo seed data and exercises the main Livewire actions. */
class WebPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function as(string $phone): static
    {
        return $this->actingAs(User::where('phone', $phone)->firstOrFail());
    }

    public function test_public_pages_render(): void
    {
        $this->get('/')->assertOk()->assertSee('Royal Snacks')->assertSee('Stock your shelves');
        $this->get('/login')->assertOk()->assertSee('Welcome back');
        $this->get('/register')->assertOk()->assertSee('About you');
        $this->get('/forgot-password')->assertOk();
        $this->get('/portal')->assertRedirect('/login');
        $this->get('/staff/admin')->assertRedirect('/login');
    }

    public function test_login_redirects_each_role_home(): void
    {
        foreach (['9100000001' => '/portal', '9000000001' => '/staff/admin', '9000000002' => '/staff/orders', '9000000010' => '/staff/packaging', '9000000011' => '/staff/warehouse', '9000000012' => '/staff/delivery', '9000000003' => '/staff/support'] as $phone => $home) {
            Livewire::test(Pages\Auth\Login::class)->set('phone', $phone)->set('password', 'password')->call('login')->assertRedirect($home);
            auth()->logout();
        }
        Livewire::test(Pages\Auth\Login::class)->set('phone', '9100000001')->set('password', 'wrong')->call('login')->assertHasErrors('phone');
    }

    public function test_shopkeeper_pages_render(): void
    {
        $order = User::where('phone', '9100000001')->first()->orders()->first();
        $ticket = Ticket::whereHas('user', fn ($q) => $q->where('phone', '9100000001'))->first();
        $this->as('9100000001');
        foreach (['/portal', '/portal/catalog', '/portal/catalog?q=chips', '/portal/offers', '/portal/cart', '/portal/orders', "/portal/orders/{$order->id}", '/portal/support', "/portal/support/{$ticket->id}", '/portal/profile', '/notifications'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/staff/admin')->assertForbidden();
        $this->get('/staff/orders')->assertForbidden();
    }

    public function test_pending_shop_is_blocked_from_cart_but_can_browse(): void
    {
        $this->as('9100000004');
        $this->get('/portal')->assertOk()->assertSee('awaiting approval');
        $this->get('/portal/catalog')->assertOk();
        $this->get('/portal/cart')->assertRedirect('/portal');
    }

    public function test_add_to_cart_checkout_and_cancel_through_livewire(): void
    {
        $user = User::where('phone', '9100000003')->first();
        $this->actingAs($user);
        $v = ProductVariant::where('sku', 'like', 'MASALA-INSTANT-NOODLES%')->first();

        Livewire::test(Pages\Portal\Catalog::class)->set("qty.{$v->id}", 6)->call('add', $v->id)->assertDispatched('cart-updated');
        $this->assertSame(6, $user->fresh()->cart->items()->first()->quantity);

        Livewire::test(Pages\Portal\Cart::class)->assertSee('Order summary')->call('applyCoupon', 'KIRANA200')->set('payment', 'cod')->call('place')->assertRedirect();
        $order = $user->orders()->latest('id')->first();
        $this->assertSame('placed', $order->status);
        $this->assertSame('KIRANA200', $order->coupon_code);
        $this->assertSame(1, $order->items->first()->free_quantity); // buy 6 get 1 free

        Livewire::test(Pages\Portal\OrderShow::class, ['order' => $order])->set('confirmCancel', true)->call('cancel');
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_online_payment_sandbox_in_portal(): void
    {
        $user = User::where('phone', '9100000003')->first();
        $this->actingAs($user);
        $v = ProductVariant::where('sku', 'like', 'ALOO-BHUJIA-48-48')->first() ?? ProductVariant::where('sku', 'like', 'ALOO-BHUJIA%')->first();
        \App\Models\Cart::forUser($user)->items()->create(['product_variant_id' => $v->id, 'quantity' => 5]);
        $c = Livewire::test(Pages\Portal\Cart::class)->set('payment', 'online')->call('place');
        $order = $user->orders()->latest('id')->first();

        $show = Livewire::test(Pages\Portal\OrderShow::class, ['order' => $order])->call('startPayment')
            ->set('instrument', '4000 0000 0000 0002')->call('confirmPayment');
        $this->assertSame('failed', $order->fresh()->payment_status);
        $show->call('startPayment')->call('useInstrument', 'upi', 'success@sandbox')->call('confirmPayment');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_station_dashboards_drive_an_order_end_to_end(): void
    {
        $order = Order::where('status', 'placed')->firstOrFail(); // Varanasi order from the seed
        $hub = $order->hub_id;

        $this->as('9000000002');
        $this->get('/staff/orders')->assertOk()->assertSee($order->number);
        $this->get("/staff/orders/{$order->id}")->assertOk();
        Livewire::test(Pages\Staff\OrderShow::class, ['order' => $order])->call('move', 'confirmed');
        $this->assertSame('confirmed', $order->fresh()->status);

        $packer = User::where('role', 'packaging')->where('hub_id', $hub)->first();
        $this->actingAs($packer)->get('/staff/packaging')->assertOk()->assertSee($order->number);
        $this->get('/staff/orders')->assertForbidden();
        $q = Livewire::test(Pages\Staff\OrderQueue::class, ['mode' => 'packaging']);
        $q->call('move', $order->id, 'packing')->call('move', $order->id, 'packed');
        $this->assertSame('packed', $order->fresh()->status);

        $wh = User::where('role', 'warehouse')->where('hub_id', $hub)->first();
        $this->actingAs($wh)->get('/staff/warehouse')->assertOk();
        Livewire::test(Pages\Staff\OrderQueue::class, ['mode' => 'warehouse'])->call('move', $order->id, 'dispatched');
        $order = $order->fresh();
        $this->assertSame('dispatched', $order->status);
        $this->assertNotNull($order->delivery_agent_id);

        $driver = User::find($order->delivery_agent_id);
        $this->actingAs($driver)->get('/staff/delivery')->assertOk()->assertSee($order->number);
        $d = Livewire::test(Pages\Staff\OrderQueue::class, ['mode' => 'delivery']);
        $d->call('move', $order->id, 'out_for_delivery');
        $d->set("otp.{$order->id}", '0000')->call('move', $order->id, 'delivered')->assertDispatched('toast');
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $d->set("otp.{$order->id}", $order->delivery_otp)->call('move', $order->id, 'delivered');
        $this->assertSame('delivered', $order->fresh()->status);

        // invoice is served via a signed link
        $url = URL::temporarySignedRoute('invoice.download', now()->addMinutes(5), ['order' => $order->id]);
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/invoices/{$order->id}")->assertForbidden();
    }

    public function test_packaging_staff_cannot_open_other_dashboards(): void
    {
        $this->as('9000000010');
        $this->get('/staff/warehouse')->assertForbidden();
        $this->get('/staff/delivery')->assertForbidden();
        $this->get('/staff/admin')->assertForbidden();
        $this->get('/staff/support')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $order = Order::first();
        $shop = \App\Models\Shop::first();
        $ticket = Ticket::first();
        $this->as('9000000001');
        foreach (['/staff/admin', '/staff/admin?days=7', '/staff/orders', "/staff/orders/{$order->id}", '/staff/shops', "/staff/shops/{$shop->id}", '/staff/products', '/staff/inventory', '/staff/offers', '/staff/offers?tab=deals', '/staff/team', '/staff/audit', '/staff/support', "/staff/support/{$ticket->id}", '/staff/packaging', '/staff/warehouse', '/staff/delivery'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_manager_approves_a_pending_shop_and_it_can_order(): void
    {
        $shop = \App\Models\Shop::where('status', 'pending')->first();
        $this->as('9000000002');
        Livewire::test(Pages\Staff\ShopShow::class, ['shop' => $shop])->set('creditLimit', '15000')->call('approve');
        $this->assertSame('approved', $shop->fresh()->status);
        $this->assertEquals(15000, $shop->fresh()->credit_limit);
        $this->assertTrue($shop->user->fresh()->canOrder());
        $this->assertSame(1, $shop->user->notifications()->count());

        $shop2 = \App\Models\Shop::where('status', 'approved')->first();
        Livewire::test(Pages\Staff\ShopShow::class, ['shop' => $shop2])->call('suspend')->assertHasErrors('note');
    }

    public function test_support_agent_replies_and_resolves(): void
    {
        $ticket = Ticket::where('status', 'open')->firstOrFail();
        $agent = $ticket->agent;
        $this->actingAs($agent)->get('/staff/support')->assertOk()->assertSee($ticket->subject);
        Livewire::test(Pages\Staff\Support\TicketShow::class, ['ticket' => $ticket])->set('body', 'Internal check')->set('internal', true)->call('send')
            ->set('body', 'We are on it')->call('send')->call('setStatus', 'resolved');
        $ticket = $ticket->fresh();
        $this->assertSame('resolved', $ticket->status);
        $this->assertSame(1, $ticket->messages()->where('is_internal', true)->count());
        // customers never see internal notes
        $this->actingAs($ticket->user)->get("/portal/support/{$ticket->id}")->assertOk()->assertSee('We are on it')->assertDontSee('Internal check');
    }

    public function test_registration_wizard_creates_pending_shop_and_verifies_otp(): void
    {
        $c = Livewire::test(Pages\Auth\Register::class)
            ->set('name', 'Test Owner')->set('phone', '9876500001')->set('email', 't@example.com')->set('password', 'secret123')->set('password_confirmation', 'secret123')->call('next')->assertSet('step', 2)
            ->set('shop_name', 'T Store')->set('pan', 'ABCDE1234F')->set('gstin', '09ABCDE1234F1Z5')->call('next')->assertSet('step', 3)
            ->set('address_line1', '5 Mall Road')->set('pincode', '110001')->set('city', 'Delhi')->call('submit')->assertHasErrors('pincode')
            ->set('pincode', '226010')->set('city', 'Lucknow')->call('submit')->assertSet('step', 4)
            ->set('otp', '111111')->call('verify')->assertHasErrors('otp')
            ->set('otp', '123456')->call('verify')->assertRedirect(route('portal'));
        $u = User::where('phone', '9876500001')->first();
        $this->assertNotNull($u->phone_verified_at);
        $this->assertSame('pending', $u->shop->status);
        $this->assertSame('LKO', $u->shop->hub->code);
    }
}
