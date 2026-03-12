<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE movements ALTER COLUMN comment DROP NOT NULL');
        DB::statement('ALTER TABLE cash_movement_details ALTER COLUMN comment DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE movements SET comment = '' WHERE comment IS NULL");
        DB::statement("UPDATE cash_movement_details SET comment = '' WHERE comment IS NULL");

        DB::statement('ALTER TABLE movements ALTER COLUMN comment SET NOT NULL');
        DB::statement('ALTER TABLE cash_movement_details ALTER COLUMN comment SET NOT NULL');
    }
};
