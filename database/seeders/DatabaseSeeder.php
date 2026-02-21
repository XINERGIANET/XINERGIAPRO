<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
        $this->call(BranchSeeder::class);
        $this->call(ModuleSeeder::class);
        $this->call(MenuOptionSeeder::class);
        $this->call(UserPermissionSeeder::class);
        $this->call(PaymentConceptSeeder::class);
        $this->call(UnitSeeder::class);
        $this->call(WorkshopModuleSeeder::class);
        $this->call(WorkshopMenuOptionSeeder::class);
        $this->call(WorkshopOperationsSeeder::class);
        $this->call(WorkshopChecklistSeeder::class);
        $this->call(WorkshopVehicleTypeSeeder::class);
        $this->call(WorkshopParameterSeeder::class);
        $this->call(WorkshopAssemblySeeder::class);
    }
}
