<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('workshop_movements', 'parts_replacement_report_notes')) {
                $table->text('parts_replacement_report_notes')->nullable()->after('corrective_observations');
            }
        });

        Schema::create('workshop_part_replacement_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')
                ->constrained('workshop_movements')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('old_part_name')->nullable();
            $table->string('new_part_name')->nullable();
            $table->text('old_part_notes')->nullable();
            $table->text('new_part_notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workshop_part_replacement_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_part_replacement_pair_id')
                ->constrained('workshop_part_replacement_pairs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('photo_type', 10);
            $table->string('photo_path');
            $table->string('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_part_replacement_photos');
        Schema::dropIfExists('workshop_part_replacement_pairs');

        Schema::table('workshop_movements', function (Blueprint $table) {
            if (Schema::hasColumn('workshop_movements', 'parts_replacement_report_notes')) {
                $table->dropColumn('parts_replacement_report_notes');
            }
        });
    }
};
