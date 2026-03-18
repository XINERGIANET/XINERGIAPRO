<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_vehicle_intake_inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_type_id')->constrained('vehicle_types')->onUpdate('cascade')->onDelete('cascade');
            $table->string('item_key', 80);
            $table->string('label', 255);
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['vehicle_type_id', 'item_key']);
            $table->index(['vehicle_type_id', 'order_num']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_vehicle_intake_inventory_items');
    }
};

