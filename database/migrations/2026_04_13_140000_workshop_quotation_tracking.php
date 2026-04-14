<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_quotation_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_seq')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'year']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('workshop_movements', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
            });
            DB::statement('ALTER TABLE workshop_movements MODIFY vehicle_id BIGINT UNSIGNED NULL');
            Schema::table('workshop_movements', function (Blueprint $table) {
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnUpdate()->restrictOnDelete();
            });
        } elseif (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::table('workshop_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('vehicle_id')->nullable()->change();
            });
        }

        Schema::table('workshop_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_movements', 'quotation_source')) {
                $table->string('quotation_source', 20)->default('internal')->after('status');
            }
            if (!Schema::hasColumn('workshop_movements', 'quotation_correlative')) {
                $table->string('quotation_correlative', 40)->nullable()->after('quotation_source');
            }
            if (!Schema::hasColumn('workshop_movements', 'quotation_result')) {
                $table->string('quotation_result', 20)->default('open')->after('quotation_correlative');
            }
            if (!Schema::hasColumn('workshop_movements', 'quotation_lost_reason')) {
                $table->text('quotation_lost_reason')->nullable()->after('quotation_result');
            }
            if (!Schema::hasColumn('workshop_movements', 'quotation_sent_at')) {
                $table->timestamp('quotation_sent_at')->nullable()->after('quotation_lost_reason');
            }
            if (!Schema::hasColumn('workshop_movements', 'quotation_client_email')) {
                $table->string('quotation_client_email', 255)->nullable()->after('quotation_sent_at');
            }
            if (!Schema::hasColumn('workshop_movements', 'quotation_vehicle_note')) {
                $table->string('quotation_vehicle_note', 500)->nullable()->after('quotation_client_email');
            }
        });

        Schema::table('workshop_movements', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_movements', 'quotation_correlative')) {
                $table->unique(['branch_id', 'quotation_correlative'], 'uq_workshop_quotation_correlative_branch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_movements', 'quotation_correlative')) {
                $table->dropUnique('uq_workshop_quotation_correlative_branch');
            }
        });

        Schema::table('workshop_movements', function (Blueprint $table) {
            foreach ([
                'quotation_vehicle_note',
                'quotation_client_email',
                'quotation_sent_at',
                'quotation_lost_reason',
                'quotation_result',
                'quotation_correlative',
                'quotation_source',
            ] as $col) {
                if (Schema::hasColumn('workshop_movements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            Schema::table('workshop_movements', function (Blueprint $table) {
                $table->dropForeign(['vehicle_id']);
            });
            DB::statement('ALTER TABLE workshop_movements MODIFY vehicle_id BIGINT UNSIGNED NOT NULL');
            Schema::table('workshop_movements', function (Blueprint $table) {
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->cascadeOnUpdate()->restrictOnDelete();
            });
        } elseif (in_array($driver, ['pgsql', 'sqlite'], true)) {
            Schema::table('workshop_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('vehicle_id')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('workshop_quotation_counters');
    }
};
