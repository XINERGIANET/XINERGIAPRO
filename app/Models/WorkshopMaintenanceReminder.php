<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopMaintenanceReminder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_id',
        'client_person_id',
        'last_workshop_movement_id',
        'average_frequency_days',
        'configured_period_days',
        'last_service_date',
        'next_service_date',
        'notify_at',
        'status',
        'notified_at',
    ];

    protected $casts = [
        'last_service_date' => 'date',
        'next_service_date' => 'date',
        'notify_at' => 'date',
        'notified_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function client()
    {
        return $this->belongsTo(Person::class, 'client_person_id');
    }

    public function lastWorkshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class, 'last_workshop_movement_id');
    }
}
