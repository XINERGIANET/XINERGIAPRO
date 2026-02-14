<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_branch', function (Blueprint $table) {
            $table->decimal('avg_cost', 24, 6)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('product_branch', function (Blueprint $table) {
            $table->dropColumn('avg_cost');
        });
    }
};

