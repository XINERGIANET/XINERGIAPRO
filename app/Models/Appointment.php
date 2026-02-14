<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'vehicle_id',
        'client_person_id',
        'start_at',
        'end_at',
        'reason',
        'notes',
        'technician_person_id',
        'status',
        'source',
        'movement_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function client()
    {
        return $this->belongsTo(Person::class, 'client_person_id');
    }

    public function technician()
    {
        return $this->belongsTo(Person::class, 'technician_person_id');
    }

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }
}

