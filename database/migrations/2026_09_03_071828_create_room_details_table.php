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
        Schema::create('room_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->string('size')->nullable();
            $table->string('floor')->nullable();
            $table->string('deposit')->nullable();
            $table->json('utilities')->nullable();
            $table->json('rental_terms')->nullable();
            $table->json('rules_permissions')->nullable();
            $table->json('required_documents')->nullable();
            $table->json('payment_methods')->nullable();
            $table->string('payment_cycle')->nullable();
            $table->json('contact_info')->nullable();
            $table->json('images')->nullable();
            $table->json('facilities')->nullable();
            $table->json('house_rules')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_details');
    }
};
