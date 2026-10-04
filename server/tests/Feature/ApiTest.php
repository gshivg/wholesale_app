<?php

namespace Tests\Feature;

use App\Models\Hub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Scenario;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase, Scenario;

    private array $reg = [
        'name' => 'Ramesh', 'phone' => '9876543210', 'email' => 'r@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123',
        'shop_name' => 'Ramesh Store', 'shop_type' => 'Kirana / General store', 'pan' => 'ABCDE1234F', 'gstin' => '09ABCDE1234F1Z5',
        'address_line1' => '12 Civil Lines', 'pincode' => '211001', 'city' => 'Prayagraj',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
        $this->hub->update(['code' => 'PRG']);
    }

    public function test_registration_otp_and_pending_approval_flow(): void
    {
        $this->postJson('/api/v1/auth/register', $this->reg)->assertCreated()->assertJsonPath('otp_hint', '123456');
        $this->postJson('/api/v1/auth/login', ['phone' => '9876543210', 'password' => 'secret123'])
            ->assertForbidden()->assertJsonPath('code', 'phone_unverified');
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9876543210', 'code' => '000000'])->assertStatus(422);

        $token = $this->postJson('/api/v1/auth/otp/verify', ['phone' => '9876543210', 'code' => '123456'])
            ->assertOk()->assertJsonPath('user.shop.status', 'pending')->json('token');

        $this->withToken($token)->getJson('/api/v1/cart')->assertForbidden()->assertJsonPath('code', 'shop_pending');
        $this->withToken($token)->getJson('/api/v1/products')->assertOk(); // browsing is allowed

        User::where('phone', '9876543210')->first()->shop->update(['status' => 'approved']);
        $this->app['auth']->forgetGuards(); // the test client reuses the resolved user between calls
        $this->withToken($token)->getJson('/api/v1/cart')->assertOk();
    }

    public function test_registration_validation(): void
    {
        $this->postJson('/api/v1/auth/register', array_merge($this->reg, ['pincode' => '110001']))
            ->assertStatus(422)->assertJsonValidationErrors('pincode');
        $this->postJson('/api/v1/auth/register', array_merge($this->reg, ['pan' => 'BADPAN']))
            ->assertStatus(422)->assertJsonValidationErrors('pan');
        $this->postJson('/api/v1/auth/register', array_merge($this->reg, ['gstin' => '09ZZZZZ9999Z1Z5']))
            ->assertStatus(422)->assertJsonValidationErrors('gstin');
        $this->postJson('/api/v1/auth/register', array_merge($this->reg, ['email' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_cart_checkout_and_sandbox_payment_over_api(): void
    {
        $user = $this->shopUser();
        $v = $this->variant(price: 1000, stock: 40);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/cart/items', ['variant_id' => $v->id, 'quantity' => 6])
            ->assertOk()->assertJsonPath('data.totals.total', 6720)->assertJsonPath('data.can_checkout', true);

        $res = $this->postJson('/api/v1/orders', ['payment_method' => 'online'])->assertCreated()
            ->assertJsonPath('data.status', 'placed')->assertJsonPath('data.can_pay', true);
        $payId = $res->json('payment.id');

        $this->postJson("/api/v1/payments/$payId/confirm", ['method' => 'card', 'instrument' => '4000000000000002'])
            ->assertOk()->assertJsonPath('payment.status', 'failed');
        $pay2 = $this->postJson('/api/v1/orders/' . $res->json('data.id') . '/payments')->assertOk()->json('payment.id');
        $this->postJson("/api/v1/payments/$pay2/confirm", ['method' => 'card', 'instrument' => '4111 1111 1111 1111'])
            ->assertOk()->assertJsonPath('data.payment_status', 'paid');

        $this->getJson('/api/v1/orders/' . $res->json('data.id'))->assertOk()->assertJsonCount(7, 'data.stages');
        $this->getJson('/api/v1/orders/' . $res->json('data.id') . '/invoice-link')->assertNotFound(); // not confirmed yet
    }

    public function test_cannot_see_another_shops_order_or_ticket(): void
    {
        $a = $this->shopUser('9111111111');
        $b = $this->shopUser('9222222222');
        $v = $this->variant(price: 1000);
        $cart = \App\Models\Cart::forUser($a);
        $cart->items()->create(['product_variant_id' => $v->id, 'quantity' => 6]);
        $order = app(\App\Services\OrderService::class)->place($a, 'cod');
        $ticket = app(\App\Services\TicketService::class)->open($a, ['category' => 'other', 'subject' => 's', 'message' => 'm']);

        Sanctum::actingAs($b);
        $this->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
        $this->getJson("/api/v1/tickets/{$ticket->id}")->assertNotFound();
    }

    public function test_ticket_creation_with_photo_and_least_loaded_assignment(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $s1 = $this->staff(\App\Enums\Role::Support, '9000000011');
        $s2 = $this->staff(\App\Enums\Role::Support, '9000000012');
        $s1->update(['name' => 'Agent One']);
        $s2->update(['name' => 'Agent Two']);
        $user = $this->shopUser();
        Sanctum::actingAs($user);

        $payload = fn () => ['category' => 'damaged', 'subject' => 'Broken', 'message' => 'Crushed cartons', 'photos' => [UploadedFile::fake()->image('a.jpg')]];
        $first = $this->post('/api/v1/tickets', $payload())->assertCreated()->json('data');
        $second = $this->post('/api/v1/tickets', $payload())->assertCreated()->json('data');

        $this->assertNotSame($first['agent_name'], $second['agent_name']); // spread across agents
        $this->assertSame('high', $first['priority']);
        $this->assertCount(1, $first['messages'][0]['attachments']);

        // agent replies → in_progress; customer reply on resolved reopens
        $ticket = \App\Models\Ticket::find($first['id']);
        $svc = app(\App\Services\TicketService::class);
        $svc->reply($ticket, $ticket->agent, 'On it');
        $this->assertSame('in_progress', $ticket->fresh()->status);
        $svc->setStatus($ticket->fresh(), 'resolved', $ticket->agent);
        $this->postJson("/api/v1/tickets/{$ticket->id}/messages", ['message' => 'Still broken'])->assertOk()->assertJsonPath('data.status', 'open');
    }

    public function test_meta_exposes_pkl_config(): void
    {
        $this->getJson('/api/v1/meta')->assertOk()
            ->assertJsonPath('brand.name', 'Royal Snacks')->assertJsonCount(7, 'orders.stages')
            ->assertJsonCount(1, 'locales');
    }
}
