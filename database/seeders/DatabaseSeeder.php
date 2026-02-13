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
    }
}
