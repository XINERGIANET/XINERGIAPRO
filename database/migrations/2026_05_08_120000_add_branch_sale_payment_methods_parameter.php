<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $categoryId = DB::table('parameter_categories')
            ->whereRaw('LOWER(description) = ?', ['ventas'])
            ->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => 'Ventas',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $parameterId = DB::table('parameters')
            ->where('description', 'Medios de pago elegidos')
            ->value('id');

        if (!$parameterId) {
            $parameterId = DB::table('parameters')->insertGetId([
                'description' => 'Medios de pago elegidos',
                'value' => '__all__',
                'status' => 1,
                'parameter_category_id' => $categoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('parameters')->where('id', $parameterId)->update([
                'value' => '__all__',
                'status' => 1,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);
        }

        $branches = DB::table('branches')->pluck('id');
        foreach ($branches as $branchId) {
            $exists = DB::table('branch_parameters')
                ->where('parameter_id', $parameterId)
                ->where('branch_id', $branchId)
                ->first();

            if ($exists) {
                DB::table('branch_parameters')->where('id', $exists->id)->update([
                    'value' => $exists->value ?: '__all__',
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('branch_parameters')->insert([
                'value' => '__all__',
                'parameter_id' => $parameterId,
                'branch_id' => $branchId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $parameterIds = DB::table('parameters')
            ->where('description', 'Medios de pago elegidos')
            ->pluck('id');

        DB::table('branch_parameters')->whereIn('parameter_id', $parameterIds)->delete();
        DB::table('parameters')->whereIn('id', $parameterIds)->delete();
    }
};
