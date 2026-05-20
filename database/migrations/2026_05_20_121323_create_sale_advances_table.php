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
        Schema::create('sale_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('final_movement_id');
            $table->unsignedBigInteger('advance_movement_id');
            $table->decimal('applied_amount', 12, 4);
            $table->timestamps();

            $table->foreign('final_movement_id')->references('id')->on('movements')->onDelete('cascade');
            $table->foreign('advance_movement_id')->references('id')->on('movements')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_advances');
    }
};
