<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopVehicleLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'vehicle_id',
        'workshop_movement_id',
        'mileage',
        'log_type',
        'notes',
        'created_by',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
