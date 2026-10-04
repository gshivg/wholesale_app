<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->string('type', 12)->default('dc'); // main | dc
            $table->string('city');
            $table->string('district');
            $table->string('pincode', 6)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('parent_hub_id')->nullable()->constrained('hubs')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hubs');
    }
};
