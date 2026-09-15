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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('bill_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD'); // USD or KHR
            $table->string('payment_type')->default('booking_deposit'); // booking_deposit, rent, general
            $table->string('status')->default('pending'); // pending, completed, failed, expired
            $table->text('qr_string');
            $table->string('md5', 64)->index();
            $table->string('bakong_hash')->nullable()->index();
            $table->string('bakong_account_id');
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('description')->nullable();
            $table->json('payment_details')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
