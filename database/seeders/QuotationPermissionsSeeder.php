<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuotationPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $ventasOptionId = 14; // ID de la opción "Ventas"
        $cotizacionesOptionId = DB::table('menu_option')->where('name', 'Cotizaciones')->value('id');

        if (!$cotizacionesOptionId) {
            $this->command->error('No se encontró la opción de menú "Cotizaciones".');
            return;
        }

        // Obtener todos los perfiles y sucursales que tienen acceso a Ventas
        $permissions = DB::table('user_permission')
            ->where('menu_option_id', $ventasOptionId)
            ->get();

        $count = 0;
        foreach ($permissions as $perm) {
            // Verificar si ya existe el permiso para Cotizaciones
            $exists = DB::table('user_permission')
                ->where('profile_id', $perm->profile_id)
                ->where('branch_id', $perm->branch_id)
                ->where('menu_option_id', $cotizacionesOptionId)
                ->exists();

            if (!$exists) {
                DB::table('user_permission')->insert([
                    'profile_id' => $perm->profile_id,
                    'branch_id' => $perm->branch_id,
                    'menu_option_id' => $cotizacionesOptionId,
                    'status' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $count++;
            }
        }

        $this->command->info("Se otorgaron $count nuevos permisos para 'Cotizaciones'.");
        
        // Limpiar caché de menú si existe
        try {
            \Illuminate\Support\Facades\Cache::flush();
            $this->command->info('Caché del sistema limpiada.');
        } catch (\Exception $e) {
            $this->command->warn('No se pudo limpiar la caché.');
        }
    }
}
