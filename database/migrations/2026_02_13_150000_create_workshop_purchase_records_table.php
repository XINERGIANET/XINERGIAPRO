<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_purchase_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->unique()->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('supplier_person_id')->constrained('people')->onUpdate('cascade')->onDelete('restrict');
            $table->string('document_kind', 30);
            $table->string('series', 20)->nullable();
            $table->string('document_number', 50);
            $table->string('currency', 10)->default('PEN');
            $table->decimal('igv_rate', 10, 4)->default(18);
            $table->decimal('subtotal', 24, 6)->default(0);
            $table->decimal('igv', 24, 6)->default(0);
            $table->decimal('total', 24, 6)->default(0);
            $table->date('issued_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'issued_at']);
            $table->unique(['branch_id', 'document_kind', 'series', 'document_number'], 'workshop_purchase_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_purchase_records');
    }
};

