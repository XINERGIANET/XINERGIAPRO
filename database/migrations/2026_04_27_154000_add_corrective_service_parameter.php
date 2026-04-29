<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categoryDescription = 'SERVICIO';
        $categoryId = DB::table('parameter_categories')
            ->where('description', $categoryDescription)
            ->whereNull('deleted_at')
            ->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => $categoryDescription,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $parameterDescription = 'Agregar Funcionalidad servicio correctivo';
        
        $existingId = DB::table('parameters')
            ->where('description', $parameterDescription)
            ->where('parameter_category_id', $categoryId)
            ->whereNull('deleted_at')
            ->value('id');

        if (!$existingId) {
            DB::table('parameters')->insert([
                'description' => $parameterDescription,
                'value' => 'No',
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
        $categoryDescription = 'SERVICIO';
        $categoryId = DB::table('parameter_categories')
            ->where('description', $categoryDescription)
            ->value('id');

        if ($categoryId) {
            DB::table('parameters')
                ->where('description', 'Agregar Funcionalidad servicio correctivo')
                ->where('parameter_category_id', $categoryId)
                ->delete();
        }
    }
};
