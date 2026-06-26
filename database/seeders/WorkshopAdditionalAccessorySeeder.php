<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkshopAdditionalAccessorySeeder extends Seeder
{
    public function run(): void
    {
        $defaultAccessories = [
            'BOTIQUIN',
            'EXTINTOR',
            'TRIANGULO',
            'CABLE BATERIA',
            'CABLE REMOLQUE',
            'LLAVEROS',
        ];

        $branches = DB::table('branches')->get(['id', 'company_id']);

        foreach ($branches as $branch) {
            $orderNum = 0;
            foreach ($defaultAccessories as $name) {
                $orderNum++;
                $exists = DB::table('workshop_additional_accessories')
                    ->where('company_id', $branch->company_id)
                    ->where('branch_id', $branch->id)
                    ->where('name', $name)
                    ->whereNull('deleted_at')
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('workshop_additional_accessories')->insert([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'order_num' => $orderNum,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
