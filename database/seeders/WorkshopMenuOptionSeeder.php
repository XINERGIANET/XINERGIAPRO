<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopMenuOptionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $moduleId = DB::table('modules')->where('name', 'Taller')->value('id');
        if (!$moduleId) {
            return;
        }

        $items = [
            ['name' => 'Tablero Mantenimiento', 'icon' => 'ri-dashboard-2-line', 'action' => 'workshop.maintenance-board.index', 'view' => 'TAL_TAB'],
            ['name' => 'Clientes Taller', 'icon' => 'ri-user-3-line', 'action' => 'workshop.clients.index', 'view' => 'TAL_CLI'],
            ['name' => 'Agenda/Citas', 'icon' => 'ri-calendar-event-line', 'action' => 'workshop.appointments.index', 'view' => 'TAL_CITAS'],
            ['name' => 'Vehiculos', 'icon' => 'ri-motorbike-line', 'action' => 'workshop.vehicles.index', 'view' => 'TAL_VEH'],
            ['name' => 'Tipos de Vehiculo', 'icon' => 'ri-steering-2-line', 'action' => 'workshop.vehicle-types.index', 'view' => 'TAL_VTYPE'],
            ['name' => 'Ordenes de Servicio', 'icon' => 'ri-file-list-3-line', 'action' => 'workshop.orders.index', 'view' => 'TAL_OS'],
            ['name' => 'Servicios Taller', 'icon' => 'ri-settings-4-line', 'action' => 'workshop.services.index', 'view' => 'TAL_SERV'],
            ['name' => 'Compras Taller', 'icon' => 'ri-file-list-2-line', 'action' => 'workshop.purchases.index', 'view' => 'TAL_COMP'],
            ['name' => 'Ventas Taller', 'icon' => 'ri-file-chart-line', 'action' => 'workshop.sales-register.index', 'view' => 'TAL_VENT'],
            ['name' => 'Armados Taller', 'icon' => 'ri-hammer-line', 'action' => 'workshop.assemblies.index', 'view' => 'TAL_ARM'],
            ['name' => 'Reportes Taller', 'icon' => 'ri-bar-chart-2-line', 'action' => 'workshop.reports.index', 'view' => 'TAL_REP'],
        ];

        foreach ($items as $item) {
            $viewId = DB::table('views')->where('abbreviation', $item['view'])->value('id');
            if (!$viewId) {
                continue;
            }

            $existing = DB::table('menu_option')
                ->where('module_id', $moduleId)
                ->where('name', $item['name'])
                ->first();

            if ($existing) {
                DB::table('menu_option')->where('id', $existing->id)->update([
                    'icon' => $item['icon'],
                    'action' => $item['action'],
                    'view_id' => $viewId,
                    'status' => 1,
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('menu_option')->insert([
                'name' => $item['name'],
                'icon' => $item['icon'],
                'action' => $item['action'],
                'view_id' => $viewId,
                'module_id' => $moduleId,
                'status' => 1,
                'quick_access' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

