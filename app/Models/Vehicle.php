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
        'vehicle_type_id',
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
        'engine_displacement_cc',
        'soat_vencimiento',
        'revision_tecnica_vencimiento',
        'status',
    ];

    protected $casts = [
        'year' => 'integer',
        'current_mileage' => 'integer',
        'engine_displacement_cc' => 'integer',
        'soat_vencimiento' => 'date',
        'revision_tecnica_vencimiento' => 'date',
    ];

    public function getDocumentStatus($date)
    {
        if (!$date) return ['label' => 'AL DIA', 'color' => 'success', 'icon' => 'ri-checkbox-circle-line'];
        
        $today = now()->startOfDay();
        $date = \Illuminate\Support\Carbon::parse($date)->startOfDay();
        
        if ($date->lt($today)) {
            return ['label' => 'VENCIDO', 'color' => 'danger', 'icon' => 'ri-error-warning-line'];
        }
        
        if ($date->diffInDays($today) <= 30) {
            return ['label' => 'POR VENCER', 'color' => 'warning', 'icon' => 'ri-alert-line'];
        }
        
        return ['label' => 'AL DIA', 'color' => 'success', 'icon' => 'ri-checkbox-circle-line'];
    }

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

    public function vehicleType()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
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

