<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $categoryId = DB::table('parameter_categories')
            ->whereRaw('UPPER(description) = ?', ['TALLER'])
            ->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => 'Taller',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $description = 'Esperar inicio del tecnico asignado';
        $parameter = DB::table('parameters')
            ->where('description', $description)
            ->where('parameter_category_id', $categoryId)
            ->first();

        if ($parameter) {
            $parameterId = (int) $parameter->id;
            DB::table('parameters')->where('id', $parameterId)->update([
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);
        } else {
            $parameterId = (int) DB::table('parameters')->insertGetId([
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
                ->exists();

            if ($exists) {
                DB::table('branch_parameters')
                    ->where('parameter_id', $parameterId)
                    ->where('branch_id', $branchId)
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ]);
                continue;
            }

            DB::table('branch_parameters')->insert([
                'value' => 'No',
                'parameter_id' => $parameterId,
                'branch_id' => $branchId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $parameterId = DB::table('parameters')
            ->where('description', 'Esperar inicio del tecnico asignado')
            ->value('id');

        if (!$parameterId) {
            return;
        }

        DB::table('branch_parameters')->where('parameter_id', $parameterId)->delete();
        DB::table('parameters')->where('id', $parameterId)->delete();
    }
};
