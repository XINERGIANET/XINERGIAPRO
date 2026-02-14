<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopMovementDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'line_type',
        'stock_status',
        'service_id',
        'product_id',
        'description',
        'qty',
        'reserved_qty',
        'unit_price',
        'discount_amount',
        'tax_rate_id',
        'subtotal',
        'tax',
        'total',
        'technician_person_id',
        'warehouse_movement_id',
        'sales_movement_id',
        'stock_consumed',
        'consumed_at',
    ];

    protected $casts = [
        'qty' => 'decimal:6',
        'reserved_qty' => 'decimal:6',
        'unit_price' => 'decimal:6',
        'discount_amount' => 'decimal:6',
        'subtotal' => 'decimal:6',
        'tax' => 'decimal:6',
        'total' => 'decimal:6',
        'stock_consumed' => 'boolean',
        'consumed_at' => 'datetime',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function service()
    {
        return $this->belongsTo(WorkshopService::class, 'service_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function technician()
    {
        return $this->belongsTo(Person::class, 'technician_person_id');
    }

    public function warehouseMovement()
    {
        return $this->belongsTo(WarehouseMovement::class);
    }

    public function sale()
    {
        return $this->belongsTo(SalesMovement::class, 'sales_movement_id');
    }

    public function reservations()
    {
        return $this->hasMany(WorkshopStockReservation::class, 'workshop_movement_detail_id');
    }

    public function warranties()
    {
        return $this->hasMany(WorkshopWarranty::class, 'workshop_movement_detail_id');
    }
}

