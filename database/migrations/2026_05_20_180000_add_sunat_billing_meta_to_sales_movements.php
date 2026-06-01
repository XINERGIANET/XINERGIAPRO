<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_movements', function (Blueprint $table) {
            $table->json('sunat_billing_meta')
                ->nullable()
                ->after('payment_type')
                ->comment('Crédito, detracción y retención para emisión electrónica SUNAT');
        });
    }

    public function down(): void
    {
        Schema::table('sales_movements', function (Blueprint $table) {
            $table->dropColumn('sunat_billing_meta');
        });
    }
};
