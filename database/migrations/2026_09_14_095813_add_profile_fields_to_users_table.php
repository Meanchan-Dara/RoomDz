<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'phone')) $table->string('phone')->nullable();
            if (!Schema::hasColumn('users', 'avatar')) $table->string('avatar')->nullable();
            if (!Schema::hasColumn('users', 'google_id')) $table->string('google_id')->nullable()->index();
            if (!Schema::hasColumn('users', 'is_verified')) $table->boolean('is_verified')->default(false);
            if (!Schema::hasColumn('users', 'location_tag')) $table->string('location_tag')->nullable();
            if (!Schema::hasColumn('users', 'telegram')) $table->string('telegram')->nullable();
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
