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
            $table->boolean('is_terciarizado')->default(false)->after('active');
        });

        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->string('last_status')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropColumn('is_terciarizado');
        });

        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->dropColumn('last_status');
        });
    }
};
