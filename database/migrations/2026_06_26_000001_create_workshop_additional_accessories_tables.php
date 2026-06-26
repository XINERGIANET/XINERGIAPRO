<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_additional_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->string('name', 120);
            $table->unsignedInteger('order_num')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'name']);
            $table->index(['branch_id', 'active', 'order_num']);
        });

        Schema::create('workshop_movement_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('accessory_id')->nullable()->constrained('workshop_additional_accessories')->onUpdate('cascade')->onDelete('set null');
            $table->string('name', 120);
            $table->boolean('present')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_movement_id', 'name']);
            $table->index(['workshop_movement_id', 'present']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_movement_accessories');
        Schema::dropIfExists('workshop_additional_accessories');
    }
};
