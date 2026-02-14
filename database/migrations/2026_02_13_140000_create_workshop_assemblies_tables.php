<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_assembly_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onUpdate('cascade')->onDelete('set null');
            $table->string('brand_company', 120)->default('GP MOTOS');
            $table->string('vehicle_type', 60);
            $table->decimal('unit_cost', 24, 6)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'branch_id', 'brand_company', 'vehicle_type'], 'workshop_assembly_costs_unique');
        });

        Schema::create('workshop_assemblies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->string('brand_company', 120)->default('GP MOTOS');
            $table->string('vehicle_type', 60);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_cost', 24, 6)->default(0);
            $table->decimal('total_cost', 24, 6)->default(0);
            $table->date('assembled_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onUpdate('cascade')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'branch_id', 'assembled_at']);
            $table->index(['branch_id', 'brand_company', 'vehicle_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_assemblies');
        Schema::dropIfExists('workshop_assembly_costs');
    }
};

