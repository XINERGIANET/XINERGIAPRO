<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_assemblies', 'workshop_assembly_location_id')) {
                $table->foreignId('workshop_assembly_location_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('workshop_assembly_locations')
                    ->onUpdate('cascade')
                    ->onDelete('set null');
            }

            if (!Schema::hasColumn('workshop_assemblies', 'responsible_technician_person_id')) {
                $table->foreignId('responsible_technician_person_id')
                    ->nullable()
                    ->after('vin')
                    ->constrained('people')
                    ->onUpdate('cascade')
                    ->onDelete('set null');
            }

            if (!Schema::hasColumn('workshop_assemblies', 'estimated_delivery_at')) {
                $table->dateTime('estimated_delivery_at')->nullable()->after('entry_at');
            }

            if (!Schema::hasColumn('workshop_assemblies', 'estimated_minutes')) {
                $table->unsignedInteger('estimated_minutes')->default(0)->after('estimated_delivery_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_assemblies', function (Blueprint $table) {
            foreach (['workshop_assembly_location_id', 'responsible_technician_person_id'] as $foreign) {
                try {
                    $table->dropForeign([$foreign]);
                } catch (\Throwable $e) {
                }
            }

            foreach ([
                'workshop_assembly_location_id',
                'responsible_technician_person_id',
                'estimated_delivery_at',
                'estimated_minutes',
            ] as $column) {
                if (Schema::hasColumn('workshop_assemblies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
