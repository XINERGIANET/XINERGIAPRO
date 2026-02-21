<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class WorkshopVehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'MOTO LINEAL',
            'MOTO DEPORTIVA',
            'SCOOTER',
            'TRIMOTO',
            'MOTOTAXI',
            'CUATRIMOTO',
            'BICIMOTO',
            'AUTO',
            'CAMIONETA',
            'FURGON',
            'CAMION',
            'BUS',
            'MINIVAN',
            'OTRO',
        ];

        foreach ($types as $index => $name) {
            VehicleType::query()->updateOrCreate(
                [
                    'company_id' => null,
                    'branch_id' => null,
                    'name' => $name,
                ],
                [
                    'order_num' => $index + 1,
                    'active' => true,
                ]
            );
        }
    }
}
