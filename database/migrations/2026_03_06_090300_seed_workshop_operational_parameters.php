<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $categories = [
            'VENTAS' => [
                ['description' => 'Caja ventas del dia', 'value' => ''],
            ],
            'CAJA' => [
                ['description' => 'Caja facturacion', 'value' => ''],
            ],
            'SERVICIO' => [
                ['description' => 'Periodo de mantenimiento (dias)', 'value' => '30'],
                ['description' => 'Dias previos de recordatorio mantenimiento', 'value' => '3'],
            ],
        ];

        foreach ($categories as $categoryDescription => $parameters) {
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

            foreach ($parameters as $parameter) {
                $existingId = DB::table('parameters')
                    ->where('description', $parameter['description'])
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
    }

    public function down(): void
    {
        DB::table('parameters')->whereIn('description', [
            'Caja ventas del dia',
            'Caja facturacion',
            'Periodo de mantenimiento (dias)',
            'Dias previos de recordatorio mantenimiento',
        ])->delete();
    }
};
