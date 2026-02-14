<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('cash_registers', 'branch_id')) {
            Schema::table('cash_registers', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('series')
                    ->constrained('branches')
                    ->onUpdate('cascade')
                    ->onDelete('set null');
            });

            $defaultBranchId = DB::table('branches')->orderBy('id')->value('id');
            if ($defaultBranchId) {
                DB::table('cash_registers')
                    ->whereNull('branch_id')
                    ->update(['branch_id' => $defaultBranchId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cash_registers', 'branch_id')) {
            Schema::table('cash_registers', function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
