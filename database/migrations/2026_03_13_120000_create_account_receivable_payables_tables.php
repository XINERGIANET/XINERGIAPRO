<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_receivable_payables', function (Blueprint $table) {
            $table->id();
            $table->string('number', 255);
            $table->foreignId('cash_movement_id')
                ->constrained('cash_movements')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('type', 255)->default('COBRAR')->comment('COBRAR => Ingreso, PAGAR => Egreso');
            $table->string('status', 255)->default('NUEVO')->comment('NUEVO => Nueva cuenta, PAGANDO => Se esta pagando, PAGADO => Se completo');
            $table->timestamp('date', 0);
            $table->timestamp('due_date', 0)->comment('Deberia ser pagado en la fecha');
            $table->timestamp('paid_at', 0)->nullable()->comment('Pagado en la fecha. Si hay pagos parciales, la fecha de pago es la ultima');
            $table->string('currency', 255)->default('PEN');
            $table->decimal('exchange_rate', 8, 3);
            $table->decimal('total_paid', 8, 2)->default(0);
            $table->string('situation', 1)->default('E');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('cash_movement_id');
            $table->index(['branch_id', 'type', 'status']);
        });

        Schema::create('account_receivable_payable_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_receivable_payable_id')
                ->constrained('account_receivable_payables')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('cash_movement_id')
                ->constrained('cash_movements')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->string('situation', 1)->default('E');
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_receivable_payable_id', 'branch_id'], 'arp_details_account_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_receivable_payable_details');
        Schema::dropIfExists('account_receivable_payables');
    }
};
