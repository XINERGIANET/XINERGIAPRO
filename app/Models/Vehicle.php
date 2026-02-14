<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'client_person_id',
        'type',
        'brand',
        'model',
        'year',
        'color',
        'plate',
        'vin',
        'engine_number',
        'chassis_number',
        'serial_number',
        'current_mileage',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function client()
    {
        return $this->belongsTo(Person::class, 'client_person_id');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function workshopMovements()
    {
        return $this->hasMany(WorkshopMovement::class);
    }

    public function logs()
    {
        return $this->hasMany(WorkshopVehicleLog::class);
    }
}

