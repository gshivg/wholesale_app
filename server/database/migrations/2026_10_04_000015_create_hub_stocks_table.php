<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('hub_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);   // cases on hand
            $table->integer('reserved')->default(0);   // cases promised to open orders
            $table->unsignedInteger('low_stock_at')->default(20);
            $table->timestamps();
            $table->unique(['hub_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_stocks');
    }
};
