<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_service_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_service_id')->constrained('workshop_services');
            $table->string('description', 255);
            $table->unsignedInteger('order_num')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_service_id', 'order_num'], 'wsd_service_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_service_details');
    }
};
