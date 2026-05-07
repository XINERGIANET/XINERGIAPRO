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
            if (!Schema::hasColumn('workshop_movements', 'driver_name')) {
                $table->string('driver_name')->nullable()->after('client_person_id');
            }
            if (!Schema::hasColumn('workshop_movements', 'driver_phone')) {
                $table->string('driver_phone')->nullable()->after('driver_name');
            }
        });

        // Add system parameter
        $categoryId = DB::table('parameter_categories')->where('description', 'SERVICIO')->value('id');
        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => 'SERVICIO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $paramDesc = 'Habilitar registro de chofer en OS';
        $existingParam = DB::table('parameters')->where('description', $paramDesc)->first();
        if (!$existingParam) {
            DB::table('parameters')->insert([
                'description' => $paramDesc,
                'value' => 'NO',
                'parameter_category_id' => $categoryId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->dropColumn(['driver_phone']);
            // We don't drop driver_name if it was already there, but for simplicity:
            // $table->dropColumn(['driver_name']); 
        });

        DB::table('parameters')->where('description', 'Habilitar registro de chofer en OS')->delete();
    }
};
