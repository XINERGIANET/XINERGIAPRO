<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->string('event', 80);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['workshop_movement_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_audits');
    }
};

