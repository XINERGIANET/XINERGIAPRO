<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();

        $categoryIds = DB::table('parameter_categories')
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(description)) = ?', ['ventas'])
            ->orderBy('id')
            ->pluck('id');

        if ($categoryIds->isEmpty()) {
            return;
        }

        $canonicalId = (int) $categoryIds->first();
        $duplicateIds = $categoryIds->slice(1)->values()->all();

        if ($duplicateIds !== []) {
            DB::table('parameters')
                ->whereIn('parameter_category_id', $duplicateIds)
                ->whereNull('deleted_at')
                ->update([
                    'parameter_category_id' => $canonicalId,
                    'updated_at' => $now,
                ]);

            DB::table('parameter_categories')
                ->whereIn('id', $duplicateIds)
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        $parameterId = DB::table('parameters')
            ->whereIn('description', [
                'Facturación con pago anticipado',
                'Facturacion con pago anticipado',
            ])
            ->whereNull('deleted_at')
            ->value('id');

        if ($parameterId) {
            DB::table('parameters')
                ->where('id', $parameterId)
                ->update([
                    'parameter_category_id' => $canonicalId,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // No reversible de forma segura sin recrear categorías duplicadas.
    }
};
