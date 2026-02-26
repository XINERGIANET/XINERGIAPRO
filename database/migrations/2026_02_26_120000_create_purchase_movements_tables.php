<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_movements', function (Blueprint $table) {
            $table->id();
            $table->string('series', 255);
            $table->string('year', 255);
            $table->string('detail_type', 255)->default('DETALLADO')->comment('DETALLADO, GLOSA');
            $table->string('includes_tax', 1)->default('N')->comment('S => El total incluye IGV, N => El total no incluye IGV');
            $table->string('payment_type', 255)->default('CONTADO')->comment('CONTADO, CREDITO');
            $table->string('affects_cash', 1)->default('N')->comment('S => Tiene movimiento de caja, N => Solo informativo');
            $table->string('currency', 255)->default('PEN');
            $table->decimal('exchange_rate', 8, 3);
            $table->decimal('subtotal', 8, 2);
            $table->decimal('tax', 8, 2);
            $table->decimal('total', 8, 2)->comment('subtotal + igv');
            $table->string('affects_kardex', 1)->default('S')->comment('S => Genera registro de kárdex, N => Solo informativo');
            $table->foreignId('movement_id')->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'year']);
            $table->index(['movement_id']);
        });

        Schema::create('purchase_movement_details', function (Blueprint $table) {
            $table->id();
            $table->string('detail_type', 255)->default('DETALLADO')->comment('DETALLADO, GLOSA');
            $table->foreignId('purchase_movement_id')->constrained('purchase_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('code', 255);
            $table->text('description');
            $table->foreignId('product_id')->nullable()->constrained('products')->onUpdate('cascade')->nullOnDelete();
            $table->json('product_json')->nullable();
            $table->foreignId('unit_id')->constrained('units')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->onUpdate('cascade')->nullOnDelete();
            $table->decimal('quantity', 24, 6);
            $table->decimal('amount', 24, 6);
            $table->text('comment')->default('');
            $table->string('status', 1)->default('E');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['purchase_movement_id']);
            $table->index(['branch_id']);
            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_movement_details');
        Schema::dropIfExists('purchase_movements');
    }
};
