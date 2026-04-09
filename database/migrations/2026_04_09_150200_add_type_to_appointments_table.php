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
        Schema::table('appointments', function (Blueprint $table) {
            $table->string('type', 20)->default('service')->after('branch_id');
            // Usamos raw SQL para asegurar la compatibilidad con PostgreSQL al cambiar nulabilidad
        });

        DB::statement('ALTER TABLE appointments ALTER COLUMN vehicle_id DROP NOT NULL');
        DB::statement('ALTER TABLE appointments ALTER COLUMN client_person_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        
        DB::statement('ALTER TABLE appointments ALTER COLUMN vehicle_id SET NOT NULL');
        DB::statement('ALTER TABLE appointments ALTER COLUMN client_person_id SET NOT NULL');
    }
};
