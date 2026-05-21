<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $categoryId = DB::table('parameter_categories')
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(description)) = ?', ['ventas'])
            ->orderBy('id')
            ->value('id');

        if (!$categoryId) {
            $categoryId = DB::table('parameter_categories')->insertGetId([
                'description' => 'Ventas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insertar el parámetro solo si no existe
        $exists = DB::table('parameters')
            ->whereIn('description', [
                'Facturación con pago anticipado',
                'Facturacion con pago anticipado',
            ])
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            DB::table('parameters')->insert([
                'description' => 'Facturación con pago anticipado',
                'value' => 'No',
                'parameter_category_id' => $categoryId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('parameters')
            ->whereIn('description', [
                'Facturación con pago anticipado',
                'Facturacion con pago anticipado',
            ])
            ->delete();
    }
};
