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
        Schema::table('product_statistics', function (Blueprint $table) {
            $table->unsignedInteger('add_to_cart_count')->default(0)->after('unique_users_view_count');
            $table->unsignedInteger('unique_users_add_to_cart_count')->default(0)->after('add_to_cart_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_statistics', function (Blueprint $table) {
            $table->dropColumn(['add_to_cart_count', 'unique_users_add_to_cart_count']);
        });
    }
};
