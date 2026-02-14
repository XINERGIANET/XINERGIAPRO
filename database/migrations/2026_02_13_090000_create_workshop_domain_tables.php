<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('client_person_id')->constrained('people')->onUpdate('cascade')->onDelete('restrict');
            $table->string('type', 30)->default('moto');
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('plate')->nullable();
            $table->string('vin')->nullable();
            $table->string('engine_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedBigInteger('current_mileage')->default(0);
            $table->string('status', 30)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id']);
            $table->index(['client_person_id', 'plate']);
            $table->unique(['company_id', 'plate']);
            $table->unique(['company_id', 'vin']);
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('person_id')->constrained('people')->onUpdate('cascade')->onDelete('cascade');
            $table->string('specialty')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'person_id']);
            $table->index(['company_id', 'branch_id', 'active']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('client_person_id')->constrained('people')->onUpdate('cascade')->onDelete('restrict');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('reason', 255);
            $table->text('notes')->nullable();
            $table->foreignId('technician_person_id')->nullable()->constrained('people')->onUpdate('cascade')->onDelete('set null');
            $table->string('status', 30)->default('pending');
            $table->string('source', 30)->default('manual');
            $table->foreignId('movement_id')->nullable()->constrained('movements')->onUpdate('cascade')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'start_at']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('workshop_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->string('name');
            $table->string('type', 30)->default('correctivo');
            $table->decimal('base_price', 24, 6)->default(0);
            $table->unsignedInteger('estimated_minutes')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'active']);
        });

        Schema::create('workshop_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->unique()->constrained('movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('vehicle_id')->constrained('vehicles')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('client_person_id')->constrained('people')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->onUpdate('cascade')->onDelete('set null');
            $table->dateTime('intake_date');
            $table->dateTime('delivery_date')->nullable();
            $table->unsignedBigInteger('mileage_in')->nullable();
            $table->unsignedBigInteger('mileage_out')->nullable();
            $table->boolean('tow_in')->default(false);
            $table->text('diagnosis_text')->nullable();
            $table->text('observations')->nullable();
            $table->string('status', 40)->default('draft');
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('sales_movement_id')->nullable()->constrained('sales_movements')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('cash_movement_id')->nullable()->constrained('cash_movements')->onUpdate('cascade')->onDelete('set null');
            $table->decimal('subtotal', 24, 6)->default(0);
            $table->decimal('tax', 24, 6)->default(0);
            $table->decimal('total', 24, 6)->default(0);
            $table->decimal('paid_total', 24, 6)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'status']);
        });

        Schema::create('workshop_movement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('line_type', 20);
            $table->foreignId('service_id')->nullable()->constrained('workshop_services')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('product_id')->nullable()->constrained('products')->onUpdate('cascade')->onDelete('set null');
            $table->string('description', 255);
            $table->decimal('qty', 24, 6)->default(1);
            $table->decimal('unit_price', 24, 6)->default(0);
            $table->foreignId('tax_rate_id')->nullable()->constrained('tax_rates')->onUpdate('cascade')->onDelete('set null');
            $table->decimal('subtotal', 24, 6)->default(0);
            $table->decimal('tax', 24, 6)->default(0);
            $table->decimal('total', 24, 6)->default(0);
            $table->foreignId('technician_person_id')->nullable()->constrained('people')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('warehouse_movement_id')->nullable()->constrained('warehouse_movements')->onUpdate('cascade')->onDelete('set null');
            $table->foreignId('sales_movement_id')->nullable()->constrained('sales_movements')->onUpdate('cascade')->onDelete('set null');
            $table->boolean('stock_consumed')->default(false);
            $table->dateTime('consumed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_movement_id', 'line_type']);
            $table->index(['product_id', 'stock_consumed']);
        });

        Schema::create('workshop_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('type', 30);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by')->constrained('users')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_movement_id', 'type']);
        });

        Schema::create('workshop_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('workshop_checklists')->onUpdate('cascade')->onDelete('cascade');
            $table->string('group', 60)->nullable();
            $table->string('label', 255);
            $table->string('result', 30)->nullable();
            $table->string('action', 30)->nullable();
            $table->text('observation')->nullable();
            $table->unsignedInteger('order_num')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['checklist_id', 'order_num']);
        });

        Schema::create('workshop_preexisting_damages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('side', 20);
            $table->text('description');
            $table->string('severity', 10)->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_movement_id', 'side']);
        });

        Schema::create('workshop_intake_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('item_key', 80);
            $table->boolean('present')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workshop_movement_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_intake_inventory');
        Schema::dropIfExists('workshop_preexisting_damages');
        Schema::dropIfExists('workshop_checklist_items');
        Schema::dropIfExists('workshop_checklists');
        Schema::dropIfExists('workshop_movement_details');
        Schema::dropIfExists('workshop_movements');
        Schema::dropIfExists('workshop_services');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('vehicles');
    }
};

