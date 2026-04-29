<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add "Correctivo" menu option to "Taller" module (ID 11)
        $optionId = DB::table('menu_option')->insertGetId([
            'module_id' => 11,
            'name' => 'Correctivo',
            'action' => 'workshop.maintenance-board.corrective',
            'icon' => 'ri-error-warning-line',
            'status' => 1,
            'view_id' => 16,
            'quick_access' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Grant permission to profiles that already have "Tablero de vehículos" (Option 31)
        $existingPermissions = DB::table('user_permission')
            ->where('menu_option_id', 31)
            ->whereNull('deleted_at')
            ->get();

        foreach ($existingPermissions as $permission) {
            DB::table('user_permission')->insert([
                'id' => (string) Str::uuid(),
                'name' => 'Correctivo',
                'profile_id' => $permission->profile_id,
                'branch_id' => $permission->branch_id,
                'menu_option_id' => $optionId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $option = DB::table('menu_option')
            ->where('action', 'workshop.maintenance-board.corrective')
            ->first();

        if ($option) {
            DB::table('user_permission')->where('menu_option_id', $option->id)->delete();
            DB::table('menu_option')->where('id', $option->id)->delete();
        }
    }
};
