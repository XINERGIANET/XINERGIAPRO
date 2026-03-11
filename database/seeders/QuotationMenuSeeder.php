<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuotationMenuSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $moduleId = DB::table('modules')->where('name', 'ILIKE', '%ventas%')->value('id');
        
        if (!$moduleId) {
            $this->command->error('No se encontró el módulo de Ventas.');
            return;
        }

        // Crear la vista si no existe (TAL_COTIZ para Gestión de Cotizaciones)
        $viewId = DB::table('views')->where('abbreviation', 'TAL_COTIZ')->value('id');
        if (!$viewId) {
            $viewId = DB::table('views')->insertGetId([
                'name' => 'Gestión de Cotizaciones',
                'abbreviation' => 'TAL_COTIZ',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Agregar la opción de menú
        $existing = DB::table('menu_option')
            ->where('module_id', $moduleId)
            ->where('name', 'Cotizaciones')
            ->first();

        if (!$existing) {
            DB::table('menu_option')->insert([
                'name' => 'Cotizaciones',
                'icon' => 'ri-file-search-line',
                'action' => 'admin.sales.quotations.index',
                'view_id' => $viewId,
                'module_id' => $moduleId,
                'status' => 1,
                'quick_access' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->command->info('Opción de menú "Cotizaciones" creada.');
        } else {
            DB::table('menu_option')->where('id', $existing->id)->update([
                'view_id' => $viewId,
                'action' => 'admin.sales.quotations.index',
                'status' => 1,
                'updated_at' => $now,
            ]);
            $this->command->info('Opción de menú "Cotizaciones" actualizada.');
        }
    }
}
