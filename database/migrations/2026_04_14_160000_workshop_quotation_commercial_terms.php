<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_movements', 'quotation_commercial_terms')) {
                $table->json('quotation_commercial_terms')->nullable()->after('quotation_vehicle_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_movements', 'quotation_commercial_terms')) {
                $table->dropColumn('quotation_commercial_terms');
            }
        });
    }
};
