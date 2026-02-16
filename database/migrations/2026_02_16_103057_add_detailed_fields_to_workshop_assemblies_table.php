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
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            $table->string('model', 80)->nullable()->after('vehicle_type');
            $table->string('displacement', 20)->nullable()->after('model');
            $table->string('color', 40)->nullable()->after('displacement');
            $table->string('vin', 100)->nullable()->after('color');
            $table->dateTime('entry_at')->nullable()->after('vin');
            $table->dateTime('started_at')->nullable()->after('entry_at');
            $table->dateTime('finished_at')->nullable()->after('started_at');
            $table->dateTime('exit_at')->nullable()->after('finished_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            $table->dropColumn([
                'model', 'displacement', 'color', 'vin',
                'entry_at', 'started_at', 'finished_at', 'exit_at'
            ]);
        });
    }
};
