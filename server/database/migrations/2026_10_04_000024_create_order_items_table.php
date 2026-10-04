<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained();
            $table->string('name');
            $table->string('label');
            $table->string('sku');
            $table->string('hsn_code', 8)->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('free_quantity')->default(0);
            $table->unsignedInteger('packs_per_case')->default(1);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('mrp', 10, 2);
            $table->decimal('gross', 12, 2);
            $table->decimal('deal_discount', 12, 2)->default(0);
            $table->decimal('slab_discount', 12, 2)->default(0);
            $table->decimal('coupon_discount', 12, 2)->default(0);
            $table->decimal('taxable', 12, 2);
            $table->decimal('gst_rate', 4, 2);
            $table->decimal('tax', 12, 2);
            $table->decimal('total', 12, 2);
            $table->string('deal_title')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
