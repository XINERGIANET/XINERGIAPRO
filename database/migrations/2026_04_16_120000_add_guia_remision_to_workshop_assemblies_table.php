<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_assemblies', 'guia_remision')) {
                $table->string('guia_remision', 120)->nullable()->after('vin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_assemblies', 'guia_remision')) {
                $table->dropColumn('guia_remision');
            }
        });
    }
};
