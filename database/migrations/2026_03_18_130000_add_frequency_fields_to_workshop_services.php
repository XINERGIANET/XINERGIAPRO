<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->unsignedBigInteger('frequency_each_km')->nullable()->after('estimated_minutes');
            $table->boolean('frequency_enabled')->default(false)->after('frequency_each_km');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropColumn('frequency_each_km');
            $table->dropColumn('frequency_enabled');
        });
    }
};

