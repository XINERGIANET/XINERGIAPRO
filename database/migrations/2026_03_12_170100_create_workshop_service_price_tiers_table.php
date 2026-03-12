<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_service_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_service_id')
                ->constrained('workshop_services')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->unsignedInteger('max_cc');
            $table->decimal('price', 24, 6)->default(0);
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();

            $table->unique(['workshop_service_id', 'max_cc'], 'workshop_service_price_tiers_unique');
            $table->index(['workshop_service_id', 'order_num']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_service_price_tiers');
    }
};
