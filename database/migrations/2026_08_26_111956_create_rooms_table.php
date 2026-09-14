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
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('type')->default('Private Room');
            $table->decimal('price', 10, 2);
            $table->string('price_period')->default('month');
            $table->boolean('is_negotiable')->default(false);
            $table->string('status')->default('AVAILABLE NOW');
            $table->unsignedInteger('total_units')->default(1);
            $table->unsignedInteger('available_units')->default(1);
            $table->boolean('is_featured')->default(false);
            $table->string('listing_type')->default('standard'); // Values: 'standard' (Free), 'featured' (Boost), 'premium' (Top placement)
            $table->decimal('rating', 2, 1)->default(4.8);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
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
