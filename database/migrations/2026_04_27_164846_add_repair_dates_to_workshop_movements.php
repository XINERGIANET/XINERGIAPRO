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
        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->timestamp('corrective_repair_started_at')->nullable()->after('corrective_parts_delivered_at');
            $table->timestamp('corrective_repair_finished_at')->nullable()->after('corrective_repair_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->dropColumn(['corrective_repair_started_at', 'corrective_repair_finished_at']);
        });
    }
};
