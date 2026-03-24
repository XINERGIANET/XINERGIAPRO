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
        Schema::table('vehicles', function (Blueprint $blade) {
            $blade->date('soat_vencimiento')->after('engine_displacement_cc')->nullable();
            $blade->date('revision_tecnica_vencimiento')->after('soat_vencimiento')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $blade) {
            $blade->dropColumn(['soat_vencimiento', 'revision_tecnica_vencimiento']);
        });
    }
};
