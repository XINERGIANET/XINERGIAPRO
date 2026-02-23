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
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            $table->foreignId('sales_movement_id')->nullable()->constrained('sales_movements')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            $table->dropForeign(['sales_movement_id']);
            $table->dropColumn('sales_movement_id');
        });
    }
};
