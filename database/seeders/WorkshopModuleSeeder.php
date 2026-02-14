<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopModuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $moduleId = DB::table('modules')->where('name', 'Taller')->value('id');
        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update([
                'icon' => 'ri-tools-line',
                'order_num' => 9,
                'status' => 1,
                'updated_at' => $now,
            ]);
        } else {
            $moduleId = DB::table('modules')->insertGetId([
                'name' => 'Taller',
                'icon' => 'ri-tools-line',
                'order_num' => 9,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $views = [
            ['name' => 'Agenda/Citas Taller', 'abbreviation' => 'TAL_CITAS'],
            ['name' => 'Vehiculos Taller', 'abbreviation' => 'TAL_VEH'],
            ['name' => 'Ordenes de Servicio Taller', 'abbreviation' => 'TAL_OS'],
            ['name' => 'Servicios Taller', 'abbreviation' => 'TAL_SERV'],
            ['name' => 'Compras Taller', 'abbreviation' => 'TAL_COMP'],
            ['name' => 'Ventas Taller', 'abbreviation' => 'TAL_VENT'],
            ['name' => 'Armados Taller', 'abbreviation' => 'TAL_ARM'],
            ['name' => 'Reportes Taller', 'abbreviation' => 'TAL_REP'],
        ];

        foreach ($views as $view) {
            $existing = DB::table('views')->where('abbreviation', $view['abbreviation'])->first();
            if ($existing) {
                DB::table('views')->where('id', $existing->id)->update([
                    'name' => $view['name'],
                    'status' => 1,
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('views')->insert([
                'name' => $view['name'],
                'abbreviation' => $view['abbreviation'],
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $workshopMovementTypeId = DB::table('movement_types')
            ->where('description', 'TALLER_OS')
            ->value('id');

        if (!$workshopMovementTypeId) {
            $workshopMovementTypeId = DB::table('movement_types')->insertGetId([
                'description' => 'TALLER_OS',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $osDocumentType = DB::table('document_types')
            ->where('movement_type_id', $workshopMovementTypeId)
            ->where('name', 'Orden de Servicio')
            ->first();

        if (!$osDocumentType) {
            DB::table('document_types')->insert([
                'name' => 'Orden de Servicio',
                'stock' => 'none',
                'movement_type_id' => $workshopMovementTypeId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}

