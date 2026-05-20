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
        Schema::table('sales_movements', function (Blueprint $table) {
            $table->boolean('is_advance')->default(false)->after('document_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_movements', function (Blueprint $table) {
            $table->dropColumn('is_advance');
        });
    }
};
