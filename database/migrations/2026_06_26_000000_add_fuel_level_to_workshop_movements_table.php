<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_movements', 'fuel_level')) {
                $table->unsignedTinyInteger('fuel_level')->nullable()->after('mileage_in');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_movements', 'fuel_level')) {
                $table->dropColumn('fuel_level');
            }
        });
    }
};
