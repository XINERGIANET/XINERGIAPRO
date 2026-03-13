<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_movements', function (Blueprint $table) {
            $table->string('billing_status', 30)->default('NOT_APPLICABLE')->after('series');
            $table->string('billing_number', 50)->nullable()->after('billing_status');
        });

        $rows = DB::table('sales_movements')
            ->join('movements', 'movements.id', '=', 'sales_movements.movement_id')
            ->join('document_types', 'document_types.id', '=', 'movements.document_type_id')
            ->select([
                'sales_movements.id',
                'sales_movements.billing_status',
                'movements.number as movement_number',
                'document_types.name as document_name',
            ])
            ->orderBy('sales_movements.id')
            ->get();

        foreach ($rows as $row) {
            $documentName = mb_strtolower((string) ($row->document_name ?? ''), 'UTF-8');
            $isInvoice = str_contains($documentName, 'factura');

            DB::table('sales_movements')
                ->where('id', $row->id)
                ->update([
                    'billing_status' => $isInvoice ? 'INVOICED' : 'NOT_APPLICABLE',
                    'billing_number' => $isInvoice ? (string) ($row->movement_number ?? '') : null,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('sales_movements', function (Blueprint $table) {
            $table->dropColumn(['billing_status', 'billing_number']);
        });
    }
};
