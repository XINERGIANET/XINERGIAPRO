<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'series',
        'year',
        'detail_type',
        'includes_tax',
        'payment_type',
        'affects_cash',
        'currency',
        'exchange_rate',
        'subtotal',
        'tax',
        'total',
        'affects_kardex',
        'movement_id',
        'branch_id',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:3',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function details()
    {
        return $this->hasMany(PurchaseMovementDetail::class, 'purchase_movement_id');
    }
}

