<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('parameters')->insert([
            'description' => 'Redirigir a pestaña de nuevo estado automáticamente',
            'value' => '1',
            'status' => 1,
            'parameter_category_id' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('parameters')->where('description', 'Redirigir a pestaña de nuevo estado automáticamente')->delete();
    }
};
