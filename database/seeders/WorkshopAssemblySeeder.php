<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopAssemblySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $branches = DB::table('branches')->select('id', 'company_id')->get();

        $baseCosts = [
            ['brand_company' => 'GP MOTOS', 'vehicle_type' => 'MOTOCICLETA', 'unit_cost' => 120],
            ['brand_company' => 'GP MOTOS', 'vehicle_type' => 'TRIMOTO CARGA', 'unit_cost' => 170],
            ['brand_company' => 'GP MOTOS', 'vehicle_type' => 'MOTOTAXI', 'unit_cost' => 180],
            ['brand_company' => 'GP MOTOS', 'vehicle_type' => 'CUATRIMOTO', 'unit_cost' => 210],
            ['brand_company' => 'MAVILA', 'vehicle_type' => 'MOTOCICLETA', 'unit_cost' => 130],
            ['brand_company' => 'MAVILA', 'vehicle_type' => 'TRIMOTO CARGA', 'unit_cost' => 175],
        ];

        foreach ($branches as $branch) {
            foreach ($baseCosts as $cost) {
                $exists = DB::table('workshop_assembly_costs')
                    ->where('company_id', $branch->company_id)
                    ->where('branch_id', $branch->id)
                    ->where('brand_company', $cost['brand_company'])
                    ->where('vehicle_type', $cost['vehicle_type'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('workshop_assembly_costs')->insert([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'brand_company' => $cost['brand_company'],
                    'vehicle_type' => $cost['vehicle_type'],
                    'unit_cost' => $cost['unit_cost'],
                    'active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}

