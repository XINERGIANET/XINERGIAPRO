<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopStockReservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_detail_id',
        'product_id',
        'branch_id',
        'qty',
        'status',
        'created_by',
        'released_at',
    ];

    protected $casts = [
        'qty' => 'decimal:6',
        'released_at' => 'datetime',
    ];

    public function detail()
    {
        return $this->belongsTo(WorkshopMovementDetail::class, 'workshop_movement_detail_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
