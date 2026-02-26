<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_movements', 'intake_client_signature_path')) {
                $table->string('intake_client_signature_path')->nullable()->after('observations');
            }
        });

        Schema::create('workshop_preexisting_damage_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_preexisting_damage_id')
                ->constrained('workshop_preexisting_damages')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('photo_path');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_preexisting_damage_photos');

        Schema::table('workshop_movements', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_movements', 'intake_client_signature_path')) {
                $table->dropColumn('intake_client_signature_path');
            }
        });
    }
};
