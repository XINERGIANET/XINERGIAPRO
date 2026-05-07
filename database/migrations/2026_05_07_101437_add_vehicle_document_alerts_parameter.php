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
        $categoryId = DB::table('parameter_categories')->where('description', 'SERVICIO')->value('id');
        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => 'SERVICIO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $paramDesc = 'Habilitar alertas de documentos de vehiculo (SOAT/RT)';
        $existingParam = DB::table('parameters')->where('description', $paramDesc)->first();
        if (!$existingParam) {
            DB::table('parameters')->insert([
                'description' => $paramDesc,
                'value' => 'Si',
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
        DB::table('parameters')->where('description', 'Habilitar alertas de documentos de vehiculo (SOAT/RT)')->delete();
    }
};
