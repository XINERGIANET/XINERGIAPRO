<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->string('name', 80);
            $table->unsignedInteger('order_num')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'active'], 'vehicle_types_scope_idx');
            $table->unique(['company_id', 'branch_id', 'name'], 'vehicle_types_scope_name_unique');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->foreignId('vehicle_type_id')
                ->nullable()
                ->after('client_person_id')
                ->constrained('vehicle_types')
                ->onUpdate('cascade')
                ->onDelete('set null');
            $table->index(['vehicle_type_id'], 'vehicles_vehicle_type_idx');
        });

        $distinctTypes = DB::table('vehicles')
            ->select('company_id', 'branch_id', 'type')
            ->whereNull('deleted_at')
            ->whereNotNull('type')
            ->whereRaw("TRIM(type) <> ''")
            ->distinct()
            ->orderBy('company_id')
            ->orderBy('branch_id')
            ->get();

        foreach ($distinctTypes as $row) {
            $typeName = trim((string) $row->type);
            if ($typeName === '') {
                continue;
            }

            $existingTypeId = DB::table('vehicle_types')
                ->where('company_id', $row->company_id)
                ->where('branch_id', $row->branch_id)
                ->where('name', $typeName)
                ->value('id');

            if (!$existingTypeId) {
                $existingTypeId = DB::table('vehicle_types')->insertGetId([
                    'company_id' => $row->company_id,
                    'branch_id' => $row->branch_id,
                    'name' => $typeName,
                    'order_num' => 0,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('vehicles')
                ->where('company_id', $row->company_id)
                ->where('branch_id', $row->branch_id)
                ->where('type', $typeName)
                ->update(['vehicle_type_id' => $existingTypeId]);
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (Schema::hasColumn('vehicles', 'vehicle_type_id')) {
                $table->dropForeign(['vehicle_type_id']);
                $table->dropIndex('vehicles_vehicle_type_idx');
                $table->dropColumn('vehicle_type_id');
            }
        });

        Schema::dropIfExists('vehicle_types');
    }
};

