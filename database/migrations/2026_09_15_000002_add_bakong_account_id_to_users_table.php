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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'bakong_account_id')) {
                $table->string('bakong_account_id')->nullable()->after('telegram');
            }
            if (!Schema::hasColumn('users', 'bakong_merchant_name')) {
                $table->string('bakong_merchant_name')->nullable()->after('bakong_account_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bakong_account_id', 'bakong_merchant_name']);
        });
    }
};
