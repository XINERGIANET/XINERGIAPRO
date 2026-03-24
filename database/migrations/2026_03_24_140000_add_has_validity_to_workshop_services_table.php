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
        Schema::table('workshop_services', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_services', 'has_validity')) {
                $table->boolean('has_validity')->default(false)->after('frequency_each_km');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropColumn('has_validity');
        });
    }
};
