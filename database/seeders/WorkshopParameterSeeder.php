<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopParameterSeeder extends Seeder
{
    public function run(): void
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

        $definitions = [
            ['description' => 'WS_ALLOW_NEGATIVE_STOCK', 'value' => '0'],
            ['description' => 'WS_ALLOW_DELIVERY_WITH_DEBT', 'value' => '0'],
            ['description' => 'WS_REQUIRE_PDI_FOR_DELIVERY', 'value' => '1'],
            ['description' => 'WS_DEFAULT_IGV', 'value' => '18'],
            ['description' => 'WS_CURRENCY', 'value' => 'PEN'],
            ['description' => 'WS_APPOINTMENT_DURATION_MIN', 'value' => '60'],
            ['description' => 'WS_MAX_ORDERS_PER_DAY', 'value' => '100'],
            ['description' => 'WS_DELAY_TOLERANCE_MIN', 'value' => '15'],
            ['description' => 'WS_REQUIRE_OPEN_CASH_SHIFT', 'value' => '1'],
            ['description' => 'WS_ALLOW_MULTI_FULL_SALES', 'value' => '0'],
            ['description' => 'WS_NO_LABOR_CHARGE_WARRANTY', 'value' => '1'],
            ['description' => 'WS_REQUIRED_CHECKLIST_TYPES', 'value' => 'PDI,OS_INTAKE'],
        ];

        $parameterIds = [];
        foreach ($definitions as $definition) {
            $parameter = DB::table('parameters')
                ->where('description', $definition['description'])
                ->where('parameter_category_id', $categoryId)
                ->first();

            if ($parameter) {
                DB::table('parameters')->where('id', $parameter->id)->update([
                    'value' => $definition['value'],
                    'status' => 1,
                    'deleted_at' => null,
                    'updated_at' => $now,
                ]);
                $parameterIds[$definition['description']] = (int) $parameter->id;
                continue;
            }

            $parameterIds[$definition['description']] = (int) DB::table('parameters')->insertGetId([
                'description' => $definition['description'],
                'value' => $definition['value'],
                'status' => 1,
                'parameter_category_id' => $categoryId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $branches = DB::table('branches')->pluck('id');
        foreach ($branches as $branchId) {
            foreach ($definitions as $definition) {
                $parameterId = $parameterIds[$definition['description']] ?? null;
                if (!$parameterId) {
                    continue;
                }

                $existing = DB::table('branch_parameters')
                    ->where('parameter_id', $parameterId)
                    ->where('branch_id', $branchId)
                    ->first();

                if ($existing) {
                    DB::table('branch_parameters')->where('id', $existing->id)->update([
                        'value' => $existing->value ?: $definition['value'],
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ]);
                    continue;
                }

                DB::table('branch_parameters')->insert([
                    'value' => $definition['value'],
                    'parameter_id' => $parameterId,
                    'branch_id' => $branchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
