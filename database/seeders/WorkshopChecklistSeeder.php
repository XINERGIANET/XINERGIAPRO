<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $branchId = DB::table('branches')->value('id');
        $companyId = DB::table('branches')->where('id', $branchId)->value('company_id');

        if (!$companyId) {
            return;
        }

        $baseServices = [
            ['name' => 'Mantenimiento basico', 'type' => 'preventivo', 'base_price' => 50, 'estimated_minutes' => 45],
            ['name' => 'Mantenimiento intermedio', 'type' => 'preventivo', 'base_price' => 90, 'estimated_minutes' => 80],
            ['name' => 'Mantenimiento general', 'type' => 'preventivo', 'base_price' => 140, 'estimated_minutes' => 120],
            ['name' => 'Mantenimiento correctivo', 'type' => 'correctivo', 'base_price' => 110, 'estimated_minutes' => 100],
        ];

        foreach ($baseServices as $service) {
            $exists = DB::table('workshop_services')
                ->where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('name', $service['name'])
                ->exists();

            if (!$exists) {
                DB::table('workshop_services')->insert([
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'name' => $service['name'],
                    'type' => $service['type'],
                    'base_price' => $service['base_price'],
                    'estimated_minutes' => $service['estimated_minutes'],
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $categoryId = $this->resolveWorkshopParameterCategoryId();

        $this->seedChecklistTemplates($categoryId, 'PDI', 'PDI', 80, ['REVISAR', 'AJUSTAR', 'CARGAR', 'COLOCAR', 'PROBAR']);
        $this->seedNamedTemplate('GP_ACTIVATION', [
            'Luces altas', 'Luces bajas', 'Direccionales', 'Claxon', 'Espejos', 'Freno delantero', 'Freno trasero',
            'Tablero', 'Bateria', 'Llaves',
        ], ['OK', 'FALTANTE', 'DAÑADO'], $categoryId);
        $this->seedNamedTemplate('MAINTENANCE', [
            'Cambio de aceite', 'Cambio de filtro', 'Limpieza de carburador', 'Ajuste de frenos', 'Lubricacion cadena',
            'Revision electrica', 'Presion de llantas', 'Prueba de ruta',
            'Mantenimiento basico', 'Mantenimiento intermedio', 'Mantenimiento general', 'Mantenimiento correctivo',
            'Limpieza inyectores', 'Reparacion cabezal', 'Escaneo', 'Cambio discos',
            'Limpieza cuerpo aceleracion', 'Lavado', 'Parchado neumatico',
        ], ['SI', 'NO'], $categoryId);
        $this->seedNamedTemplate('OS_INTAKE', [
            'Trabajo a realizar confirmado', 'Inventario recepcionado', 'Danos preexistentes registrados', 'Autorizacion firmada',
        ], ['SI', 'NO'], $categoryId);
    }

    private function resolveWorkshopParameterCategoryId(): int
    {
        $existing = DB::table('parameter_categories')
            ->whereRaw('UPPER(description) = ?', ['TALLER'])
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('parameter_categories')->insertGetId([
            'description' => 'Taller',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedChecklistTemplates(int $categoryId, string $templateType, string $group, int $items, array $actions): void
    {
        for ($i = 1; $i <= $items; $i++) {
            $key = "WS_{$templateType}_{$i}";
            $exists = DB::table('parameters')
                ->where('description', $key)
                ->where('parameter_category_id', $categoryId)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('parameters')->insert([
                'description' => $key,
                'value' => json_encode([
                    'template_type' => $templateType,
                    'group' => $group,
                    'label' => "{$templateType} punto {$i}",
                    'action' => $actions[($i - 1) % count($actions)],
                    'order_num' => $i,
                ]),
                'parameter_category_id' => $categoryId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedNamedTemplate(string $templateType, array $labels, array $results, int $categoryId): void
    {
        foreach ($labels as $index => $label) {
            $position = $index + 1;
            $key = "WS_{$templateType}_{$position}";
            $exists = DB::table('parameters')
                ->where('description', $key)
                ->where('parameter_category_id', $categoryId)
                ->exists();
            if ($exists) {
                continue;
            }

            DB::table('parameters')->insert([
                'description' => $key,
                'value' => json_encode([
                    'template_type' => $templateType,
                    'group' => $templateType,
                    'label' => $label,
                    'result_options' => $results,
                    'order_num' => $position,
                ]),
                'parameter_category_id' => $categoryId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
