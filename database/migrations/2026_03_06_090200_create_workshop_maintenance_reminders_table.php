<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_maintenance_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('client_person_id')->constrained('people')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('last_workshop_movement_id')->nullable()->constrained('workshop_movements')->onUpdate('cascade')->onDelete('set null');
            $table->unsignedInteger('average_frequency_days')->default(0);
            $table->unsignedInteger('configured_period_days')->default(0);
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->date('notify_at')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status', 'notify_at'], 'wk_maintenance_reminders_notify_idx');
            $table->unique(['branch_id', 'vehicle_id'], 'wk_maintenance_reminders_vehicle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_maintenance_reminders');
    }
};
