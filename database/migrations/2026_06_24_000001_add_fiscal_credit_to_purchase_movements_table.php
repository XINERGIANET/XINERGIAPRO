<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_movements', function (Blueprint $table) {
            $table->string('fiscal_credit', 10)->nullable()->after('affects_kardex');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_movements', function (Blueprint $table) {
            $table->dropColumn('fiscal_credit');
        });
    }
};
