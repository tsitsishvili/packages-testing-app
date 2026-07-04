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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('tracking_number');
            $table->string('carrier');
            $table->unsignedInteger('weight_grams');
            $table->decimal('declared_value', 10, 2);
            $table->unsignedSmallInteger('parcel_count');
            $table->ipAddress('origin_ip');
            $table->string('label_filename')->nullable();
            $table->timestamp('shipped_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
