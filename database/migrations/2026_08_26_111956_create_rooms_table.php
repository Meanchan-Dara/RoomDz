<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('Private Room');
            $table->decimal('price', 10, 2);
            $table->string('price_period')->default('month');
            $table->string('status')->default('AVAILABLE NOW');
            $table->decimal('rating', 2, 1)->default(4.8);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('address');
            $table->string('image')->nullable();
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
