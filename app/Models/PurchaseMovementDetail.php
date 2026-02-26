<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseMovementDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'detail_type',
        'purchase_movement_id',
        'code',
        'description',
        'product_id',
        'product_json',
        'unit_id',
        'tax_rate_id',
        'quantity',
        'amount',
        'comment',
        'status',
        'branch_id',
    ];

    protected $casts = [
        'product_json' => 'array',
        'quantity' => 'decimal:6',
        'amount' => 'decimal:6',
    ];

    public function purchaseMovement()
    {
        return $this->belongsTo(PurchaseMovement::class, 'purchase_movement_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}

