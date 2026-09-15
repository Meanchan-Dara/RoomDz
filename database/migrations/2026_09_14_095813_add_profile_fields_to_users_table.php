<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable();
            $table->string('avatar')->nullable();
            $table->string('google_id')->nullable()->index();
            $table->boolean('is_verified')->default(false);
            $table->string('location_tag')->nullable();
            $table->string('telegram')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);

            $table->dropColumn([
                'phone',
                'avatar',
                'google_id',
                'is_verified',
                'location_tag',
                'telegram',
            ]);
        });
    }
};