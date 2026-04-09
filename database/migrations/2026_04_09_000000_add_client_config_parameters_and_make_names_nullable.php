<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Hacer los campos nullable en la tabla people
        Schema::table('people', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });

        // 2. Agregar la categoría de parámetros y los parámetros de configuración
        $categoryDescription = 'CLIENTE';
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

        $parameters = [
            ['description' => 'Nombres obligatorios', 'value' => 'Si'],
            ['description' => 'Apellidos obligatorios', 'value' => 'Si'],
        ];

        foreach ($parameters as $parameter) {
            $existingId = DB::table('parameters')
                ->where('description', $parameter['description'])
                ->where('parameter_category_id', $categoryId)
                ->whereNull('deleted_at')
                ->value('id');

            if (!$existingId) {
                DB::table('parameters')->insert([
                    'description' => $parameter['description'],
                    'value' => $parameter['value'],
                    'parameter_category_id' => $categoryId,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revertimos el nullable por seguridad de datos ya guardados sin nombre
        
        // Opcional: eliminar parámetros (generalmente no se recomienda borrar datos de config en down si ya se usaron)
        /*
        $categoryId = DB::table('parameter_categories')->where('description', 'CLIENTE')->value('id');
        if ($categoryId) {
            DB::table('parameters')->where('parameter_category_id', $categoryId)->delete();
            DB::table('parameter_categories')->where('id', $categoryId)->delete();
        }
        */
    }
};
