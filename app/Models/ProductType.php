<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'icon',
        'behavior',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public static function ensureDefaultsForBranch(int $branchId): void
    {
        if ($branchId <= 0) {
            return;
        }

        $hasAnyProductType = static::query()
            ->where('branch_id', $branchId)
            ->exists();

        if ($hasAnyProductType) {
            return;
        }

        static::query()->create([
            'branch_id' => $branchId,
            'name' => 'Producto final',
            'description' => 'Productos listos para la venta.',
            'icon' => 'ri-shopping-bag-3-line',
            'behavior' => 'SELLABLE',
            'status' => true,
        ]);

        static::query()->create([
            'branch_id' => $branchId,
            'name' => 'Suministro',
            'description' => 'Repuestos, insumos o materiales de apoyo.',
            'icon' => 'ri-tools-line',
            'behavior' => 'SUPPLY',
            'status' => true,
        ]);
    }
}
