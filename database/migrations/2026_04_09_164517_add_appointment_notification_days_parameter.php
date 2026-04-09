<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Parameter Category ID for 'Taller' is 6
        $categoryId = 6;

        $parameterId = DB::table('parameters')->insertGetId([
            'description' => 'Días previos para notificar citas',
            'value' => '2',
            'status' => 1,
            'parameter_category_id' => $categoryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Also add it to existing branches (ID 1 as default)
        $branches = DB::table('branches')->pluck('id');
        foreach ($branches as $branchId) {
            DB::table('branch_parameters')->insert([
                'parameter_id' => $parameterId,
                'branch_id' => $branchId,
                'value' => '2',
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
        $parameterId = DB::table('parameters')
            ->where('description', 'Días previos para notificar citas')
            ->value('id');

        if ($parameterId) {
            DB::table('branch_parameters')->where('parameter_id', $parameterId)->delete();
            DB::table('parameters')->where('id', $parameterId)->delete();
        }
    }
};
