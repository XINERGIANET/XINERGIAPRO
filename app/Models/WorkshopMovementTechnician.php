<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopMovementTechnician extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'technician_person_id',
        'commission_percentage',
        'commission_amount',
    ];

    protected $casts = [
        'commission_percentage' => 'decimal:4',
        'commission_amount' => 'decimal:6',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function technician()
    {
        return $this->belongsTo(Person::class, 'technician_person_id');
    }
}
