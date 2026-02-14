<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('pending')->after('status');
            $table->text('approval_note')->nullable()->after('approved_by');
            $table->string('payment_status', 20)->default('pending')->after('paid_total');
            $table->timestamp('started_at')->nullable()->after('intake_date');
            $table->timestamp('finished_at')->nullable()->after('delivery_date');
            $table->timestamp('locked_at')->nullable()->after('finished_at');
            $table->foreignId('previous_workshop_movement_id')->nullable()->after('movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('set null');
            $table->index(['branch_id', 'status', 'payment_status']);
        });

        Schema::table('workshop_movement_details', function (Blueprint $table) {
            $table->string('stock_status', 20)->default('pending')->after('line_type');
            $table->decimal('reserved_qty', 24, 6)->default(0)->after('qty');
            $table->decimal('discount_amount', 24, 6)->default(0)->after('unit_price');
            $table->index(['workshop_movement_id', 'stock_status']);
        });

        Schema::create('workshop_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('user_id')->constrained('users')->onUpdate('cascade')->onDelete('restrict');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['workshop_movement_id', 'created_at']);
        });

        Schema::create('workshop_stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_detail_id')->constrained('workshop_movement_details')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('branch_id')->constrained('branches')->onUpdate('cascade')->onDelete('cascade');
            $table->decimal('qty', 24, 6);
            $table->string('status', 20)->default('reserved');
            $table->foreignId('created_by')->constrained('users')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'product_id', 'status']);
        });

        Schema::create('workshop_vehicle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('workshop_movement_id')->nullable()->constrained('workshop_movements')->onUpdate('cascade')->onDelete('set null');
            $table->unsignedBigInteger('mileage')->nullable();
            $table->string('log_type', 20)->default('UPDATE');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();

            $table->index(['vehicle_id', 'created_at']);
        });

        Schema::create('workshop_warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_movement_id')->constrained('workshop_movements')->onUpdate('cascade')->onDelete('cascade');
            $table->foreignId('workshop_movement_detail_id')->nullable()->constrained('workshop_movement_details')->onUpdate('cascade')->onDelete('set null');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status', 20)->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workshop_movement_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_warranties');
        Schema::dropIfExists('workshop_vehicle_logs');
        Schema::dropIfExists('workshop_stock_reservations');
        Schema::dropIfExists('workshop_status_histories');

        Schema::table('workshop_movement_details', function (Blueprint $table) {
            $table->dropIndex(['workshop_movement_id', 'stock_status']);
            $table->dropColumn(['stock_status', 'reserved_qty', 'discount_amount']);
        });

        Schema::table('workshop_movements', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status', 'payment_status']);
            $table->dropConstrainedForeignId('previous_workshop_movement_id');
            $table->dropColumn(['approval_status', 'approval_note', 'payment_status', 'started_at', 'finished_at', 'locked_at']);
        });
    }
};
