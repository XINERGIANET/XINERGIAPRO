<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('icon', 255)->nullable();
            $table->string('behavior', 20)->default('VENDIBLE')->comment('VENDIBLE, SUMINISTRO');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'name']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_type_id')
                ->nullable()
                ->after('type')
                ->constrained('product_types')
                ->cascadeOnUpdate()
                ->nullOnDelete();
        });

        $branchIds = DB::table('branches')->pluck('id');
        foreach ($branchIds as $branchId) {
            $sellableId = DB::table('product_types')->insertGetId([
                'branch_id' => $branchId,
                'name' => 'Producto final',
                'description' => 'Productos listos para la venta.',
                'icon' => 'ri-shopping-bag-3-line',
                'behavior' => 'SELLABLE',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $supplyId = DB::table('product_types')->insertGetId([
                'branch_id' => $branchId,
                'name' => 'Suministro',
                'description' => 'Repuestos, insumos o materiales de apoyo.',
                'icon' => 'ri-tools-line',
                'behavior' => 'SUPPLY',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $productIds = DB::table('product_branch')
                ->where('branch_id', $branchId)
                ->orderBy('product_id')
                ->pluck('product_id');

            foreach ($productIds as $productId) {
                $legacyType = strtoupper((string) DB::table('products')->where('id', $productId)->value('type'));
                DB::table('products')
                    ->where('id', $productId)
                    ->whereNull('product_type_id')
                    ->update([
                        'product_type_id' => $legacyType === 'INGREDENT' ? $supplyId : $sellableId,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_type_id');
        });

        Schema::dropIfExists('product_types');
    }
};
