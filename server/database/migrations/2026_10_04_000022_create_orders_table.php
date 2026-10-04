<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('hub_id')->constrained('hubs');
            $table->string('status', 20)->default('placed')->index();
            $table->string('payment_method', 10);
            $table->string('payment_status', 10)->default('pending'); // pending | paid | failed | refunded
            $table->decimal('gross_amount', 12, 2);       // before any discount
            $table->decimal('deal_discount', 12, 2)->default(0);
            $table->decimal('slab_discount', 12, 2)->default(0);
            $table->decimal('coupon_discount', 12, 2)->default(0);
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable();
            $table->decimal('taxable_amount', 12, 2);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->decimal('round_off', 5, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->unsignedInteger('total_cases')->default(0);
            $table->json('shipping_address');             // snapshot
            $table->text('notes')->nullable();
            $table->string('delivery_otp', 6)->nullable();
            $table->foreignId('delivery_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('packing_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['hub_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
