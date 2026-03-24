<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->string('validity_type')->nullable()->comment('soat_vencimiento o revision_tecnica_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropColumn('validity_type');
        });
    }
};
