<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_movement_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('technician_person_id')->constrained('people')->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('commission_percentage', 8, 4)->default(0);
            $table->decimal('commission_amount', 24, 6)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_movement_id', 'technician_person_id'], 'uq_workshop_movement_technician');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_movement_technicians');
    }
};
