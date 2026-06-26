<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workshop_additional_accessories')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE workshop_additional_accessories DROP CONSTRAINT IF EXISTS workshop_additional_accessories_branch_id_name_unique');
            DB::statement('CREATE INDEX IF NOT EXISTS workshop_additional_accessories_branch_id_name_index ON workshop_additional_accessories (branch_id, name)');
            return;
        }

        if ($this->mysqlIndexExists('workshop_additional_accessories_branch_id_name_unique')) {
            DB::statement('ALTER TABLE workshop_additional_accessories DROP INDEX workshop_additional_accessories_branch_id_name_unique');
        }

        if (!$this->mysqlIndexExists('workshop_additional_accessories_branch_id_name_index')) {
            DB::statement('ALTER TABLE workshop_additional_accessories ADD INDEX workshop_additional_accessories_branch_id_name_index (branch_id, name)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('workshop_additional_accessories')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS workshop_additional_accessories_branch_id_name_index');
            DB::statement('ALTER TABLE workshop_additional_accessories ADD CONSTRAINT workshop_additional_accessories_branch_id_name_unique UNIQUE (branch_id, name)');
            return;
        }

        if ($this->mysqlIndexExists('workshop_additional_accessories_branch_id_name_index')) {
            DB::statement('ALTER TABLE workshop_additional_accessories DROP INDEX workshop_additional_accessories_branch_id_name_index');
        }

        if (!$this->mysqlIndexExists('workshop_additional_accessories_branch_id_name_unique')) {
            DB::statement('ALTER TABLE workshop_additional_accessories ADD UNIQUE workshop_additional_accessories_branch_id_name_unique (branch_id, name)');
        }
    }

    private function mysqlIndexExists(string $indexName): bool
    {
        $database = (string) DB::getDatabaseName();
        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, 'workshop_additional_accessories', $indexName]
        );

        return count($rows) > 0;
    }
};
