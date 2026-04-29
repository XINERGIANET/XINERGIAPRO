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
            $table->string('service_type')->default('preventivo')->after('status');
            $table->string('corrective_phase')->nullable()->after('service_type');
            
            // Timestamps para el flujo correctivo
            $table->timestamp('corrective_reception_at')->nullable();
            $table->timestamp('corrective_scheduled_at')->nullable();
            $table->timestamp('corrective_eval_started_at')->nullable();
            $table->timestamp('corrective_eval_finished_at')->nullable();
            $table->timestamp('corrective_quote_delivered_at')->nullable();
            $table->timestamp('corrective_quote_approved_at')->nullable();
            $table->timestamp('corrective_parts_requested_at')->nullable();
            $table->timestamp('corrective_parts_delivered_at')->nullable();
            
            // Observaciones opcionales por fase
            $table->text('corrective_observations')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->dropColumn([
                'service_type',
                'corrective_phase',
                'corrective_reception_at',
                'corrective_scheduled_at',
                'corrective_eval_started_at',
                'corrective_eval_finished_at',
                'corrective_quote_delivered_at',
                'corrective_quote_approved_at',
                'corrective_parts_requested_at',
                'corrective_parts_delivered_at',
                'corrective_observations'
            ]);
        });
    }
};
