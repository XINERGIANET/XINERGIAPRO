<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_movement_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['workshop_movement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_movement_status_logs');
    }
};

