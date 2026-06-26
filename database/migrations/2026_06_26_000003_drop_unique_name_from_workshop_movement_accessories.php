<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workshop_movement_accessories')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE workshop_movement_accessories DROP CONSTRAINT IF EXISTS workshop_movement_accessories_workshop_movement_id_name_unique');
            DB::statement('CREATE INDEX IF NOT EXISTS workshop_movement_accessories_workshop_movement_id_name_index ON workshop_movement_accessories (workshop_movement_id, name)');
            return;
        }

        if ($this->mysqlIndexExists('workshop_movement_accessories_workshop_movement_id_name_unique')) {
            DB::statement('ALTER TABLE workshop_movement_accessories DROP INDEX workshop_movement_accessories_workshop_movement_id_name_unique');
        }

        if (!$this->mysqlIndexExists('workshop_movement_accessories_workshop_movement_id_name_index')) {
            DB::statement('ALTER TABLE workshop_movement_accessories ADD INDEX workshop_movement_accessories_workshop_movement_id_name_index (workshop_movement_id, name)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('workshop_movement_accessories')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS workshop_movement_accessories_workshop_movement_id_name_index');
            DB::statement('ALTER TABLE workshop_movement_accessories ADD CONSTRAINT workshop_movement_accessories_workshop_movement_id_name_unique UNIQUE (workshop_movement_id, name)');
            return;
        }

        if ($this->mysqlIndexExists('workshop_movement_accessories_workshop_movement_id_name_index')) {
            DB::statement('ALTER TABLE workshop_movement_accessories DROP INDEX workshop_movement_accessories_workshop_movement_id_name_index');
        }

        if (!$this->mysqlIndexExists('workshop_movement_accessories_workshop_movement_id_name_unique')) {
            DB::statement('ALTER TABLE workshop_movement_accessories ADD UNIQUE workshop_movement_accessories_workshop_movement_id_name_unique (workshop_movement_id, name)');
        }
    }

    private function mysqlIndexExists(string $indexName): bool
    {
        $database = (string) DB::getDatabaseName();
        $rows = DB::select(
            'SELECT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, 'workshop_movement_accessories', $indexName]
        );

        return count($rows) > 0;
    }
};
