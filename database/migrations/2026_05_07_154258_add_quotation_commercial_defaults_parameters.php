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
        $categoryId = 1; // Ventas
        $now = now();

        $parameters = [
            ['description' => 'Cotización: Tiempo de entrega', 'value' => '', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: Validez de oferta', 'value' => '5 dias habiles', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: Garantía de servicio', 'value' => '', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: Lugar de entrega', 'value' => 'Centro de servicio', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: Nota de precios', 'value' => 'IGV Incluido', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: Condición de pago', 'value' => 'Deposito en cuenta', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: Cuenta BCP', 'value' => '', 'parameter_category_id' => $categoryId],
            ['description' => 'Cotización: CCI', 'value' => '', 'parameter_category_id' => $categoryId],
        ];

        foreach ($parameters as $param) {
            $paramId = DB::table('parameters')->insertGetId(array_merge($param, [
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            // Propagate to all branches
            $branchIds = DB::table('branches')->pluck('id');
            foreach ($branchIds as $branchId) {
                DB::table('branch_parameters')->insert([
                    'parameter_id' => $paramId,
                    'branch_id' => $branchId,
                    'value' => $param['value'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $descriptions = [
            'Cotización: Tiempo de entrega',
            'Cotización: Validez de oferta',
            'Cotización: Garantía de servicio',
            'Cotización: Lugar de entrega',
            'Cotización: Nota de precios',
            'Cotización: Condición de pago',
            'Cotización: Cuenta BCP',
            'Cotización: CCI',
        ];

        $parameterIds = DB::table('parameters')
            ->whereIn('description', $descriptions)
            ->pluck('id');

        DB::table('branch_parameters')->whereIn('parameter_id', $parameterIds)->delete();
        DB::table('parameters')->whereIn('id', $parameterIds)->delete();
    }
};
