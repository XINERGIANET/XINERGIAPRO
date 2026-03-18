<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopVehicleIntakeInventoryItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_vehicle_intake_inventory_items';

    protected $fillable = [
        'vehicle_type_id',
        'item_key',
        'label',
        'order_num',
    ];
}

