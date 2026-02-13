<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'currency',
        'exchange_rate',
        'subtotal',
        'tax',
        'total',
        'people_count',
        'finished_at',
        'table_id',
        'area_id',
        'delivery_amount',
        'contact_phone',
        'delivery_address',
        'delivery_time',
        'status',
        'movement_id',
        'branch_id',
    ];

    public function details()
    {
        return $this->hasMany(OrderMovementDetail::class);
    }

    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

}
