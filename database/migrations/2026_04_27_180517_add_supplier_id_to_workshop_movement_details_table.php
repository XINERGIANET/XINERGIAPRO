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
        Schema::table('workshop_movement_details', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_person_id')->nullable()->after('technician_person_id');
            $table->foreign('supplier_person_id')->references('id')->on('people')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_movement_details', function (Blueprint $table) {
            $table->dropForeign(['supplier_person_id']);
            $table->dropColumn('supplier_person_id');
        });
    }
};
