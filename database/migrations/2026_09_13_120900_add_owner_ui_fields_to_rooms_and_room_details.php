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
        // Add fields to rooms table
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('is_negotiable')->default(false)->after('price_period');
            $table->boolean('is_featured')->default(false)->after('status');
        });

        // Add fields to room_details table
        Schema::table('room_details', function (Blueprint $table) {
            $table->json('utilities')->nullable()->after('deposit');
            $table->json('rental_terms')->nullable()->after('utilities');
            $table->json('rules_permissions')->nullable()->after('rental_terms');
            $table->json('required_documents')->nullable()->after('rules_permissions');
            $table->json('payment_methods')->nullable()->after('required_documents');
            $table->string('payment_cycle')->nullable()->after('payment_methods');
            $table->json('contact_info')->nullable()->after('payment_cycle');
        });

        // Add fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_verified')->default(false)->after('avatar');
            $table->string('location_tag')->nullable()->after('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['is_negotiable', 'is_featured']);
        });

        Schema::table('room_details', function (Blueprint $table) {
            $table->dropColumn([
                'utilities',
                'rental_terms',
                'rules_permissions',
                'required_documents',
                'payment_methods',
                'payment_cycle',
                'contact_info',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'location_tag']);
        });
    }
};
