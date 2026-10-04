<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('label');                       // e.g. "30g x 48 packs"
            $table->unsignedInteger('packs_per_case')->default(1);
            $table->decimal('price', 10, 2);               // per CASE, excluding GST
            $table->decimal('mrp', 10, 2);                 // per CASE MRP
            $table->unsignedInteger('min_qty')->default(1); // minimum cases per order line
            $table->unsignedInteger('max_qty')->default(500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
