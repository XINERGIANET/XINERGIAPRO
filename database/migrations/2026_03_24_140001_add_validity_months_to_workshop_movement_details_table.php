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
        Schema::table('workshop_movement_details', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_movement_details', 'validity_months')) {
                $table->integer('validity_months')->nullable()->after('duration_minutes')->comment('6 or 12 months for validity');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_movement_details', function (Blueprint $table) {
            $table->dropColumn('validity_months');
        });
    }
};
