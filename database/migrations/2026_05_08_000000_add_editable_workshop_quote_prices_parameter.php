<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $categoryId = DB::table('parameter_categories')
            ->where('description', 'ILIKE', 'Taller')
            ->whereNull('deleted_at')
            ->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => 'Taller',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $description = 'Permitir editar precios en cotizacion de taller';
        $parameterId = DB::table('parameters')
            ->where('description', $description)
            ->whereNull('deleted_at')
            ->value('id');

        if (!$parameterId) {
            $parameterId = DB::table('parameters')->insertGetId([
                'description' => $description,
                'value' => 'No',
                'status' => 1,
                'parameter_category_id' => $categoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('branches')->pluck('id') as $branchId) {
            $exists = DB::table('branch_parameters')
                ->where('parameter_id', $parameterId)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->exists();

            if (!$exists) {
                DB::table('branch_parameters')->insert([
                    'parameter_id' => $parameterId,
                    'branch_id' => $branchId,
                    'value' => 'No',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $parameterIds = DB::table('parameters')
            ->where('description', 'Permitir editar precios en cotizacion de taller')
            ->pluck('id');

        DB::table('branch_parameters')->whereIn('parameter_id', $parameterIds)->delete();
        DB::table('parameters')->whereIn('id', $parameterIds)->delete();
    }
};
