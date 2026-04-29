<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categoryId = DB::table('parameter_categories')->where('description', 'SERVICIO')->value('id');
        
        if ($categoryId) {
            DB::table('parameters')->insert([
                'description' => 'Habilitar función de servicio externo (Taller)',
                'value' => 'No',
                'status' => 1,
                'parameter_category_id' => $categoryId,
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
        DB::table('parameters')
            ->where('description', 'Habilitar función de servicio externo (Taller)')
            ->delete();
    }
};
