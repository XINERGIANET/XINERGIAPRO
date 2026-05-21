<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $parameterId = DB::table('parameters')
            ->whereIn('description', [
                'Facturación con pago anticipado',
                'Facturacion con pago anticipado',
            ])
            ->whereNull('deleted_at')
            ->value('id');

        if (!$parameterId) {
            return;
        }

        $defaultValue = DB::table('parameters')
            ->where('id', $parameterId)
            ->value('value') ?: 'No';

        foreach (DB::table('branches')->pluck('id') as $branchId) {
            $exists = DB::table('branch_parameters')
                ->where('parameter_id', $parameterId)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('branch_parameters')->insert([
                'parameter_id' => $parameterId,
                'branch_id' => $branchId,
                'value' => $defaultValue,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $parameterIds = DB::table('parameters')
            ->whereIn('description', [
                'Facturación con pago anticipado',
                'Facturacion con pago anticipado',
            ])
            ->pluck('id');

        if ($parameterIds->isEmpty()) {
            return;
        }

        DB::table('branch_parameters')
            ->whereIn('parameter_id', $parameterIds->all())
            ->delete();
    }
};
