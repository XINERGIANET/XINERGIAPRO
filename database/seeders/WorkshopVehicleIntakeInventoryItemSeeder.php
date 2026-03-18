<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopVehicleIntakeInventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $defaultItems = [
            'ESPEJOS' => 'Espejos',
            'FARO_DELANTERO' => 'Faro delantero',
            'DIRECCIONALES' => 'Direccionales',
            'TAPON_GASOLINA' => 'Tapon de gasolina',
            'PEDALES' => 'Pedales',
            'CLAXON' => 'Claxon',
            'ASIENTOS' => 'Asientos',
            'LUZ_STOP_TRASERA' => 'Luz stop trasera',
            'CUBIERTAS_COMPLETAS' => 'Cubiertas completas',
            'TACOMETROS' => 'Tacometros',
            'STEREO' => 'Stereo',
            'PARABRISAS' => 'Parabrisas',
            'TAPON_RADIADORES' => 'Tapon de radiadores',
            'FILTRO_AIRE' => 'Filtro de aire',
            'BATERIA' => 'Bateria',
            'LLAVES' => 'Llaves',
        ];

        $vehicleTypes = VehicleType::query()
            ->where('active', true)
            ->get(['id']);

        foreach ($vehicleTypes as $vehicleType) {
            $orderNum = 0;
            foreach ($defaultItems as $itemKey => $label) {
                $orderNum++;
                $exists = DB::table('workshop_vehicle_intake_inventory_items')
                    ->where('vehicle_type_id', $vehicleType->id)
                    ->where('item_key', $itemKey)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('workshop_vehicle_intake_inventory_items')->insert([
                    'vehicle_type_id' => $vehicleType->id,
                    'item_key' => $itemKey,
                    'label' => $label,
                    'order_num' => $orderNum,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}

