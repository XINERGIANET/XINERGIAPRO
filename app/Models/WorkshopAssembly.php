<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopAssembly extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'workshop_assembly_location_id',
        'brand_company',
        'vehicle_type',
        'model',
        'displacement',
        'color',
        'vin',
        'guia_remision',
        'responsible_technician_person_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'assembled_at',
        'entry_at',
        'estimated_delivery_at',
        'estimated_minutes',
        'started_at',
        'finished_at',
        'exit_at',
        'notes',
        'created_by',
        'sales_movement_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'assembled_at' => 'date',
        'entry_at' => 'datetime',
        'estimated_delivery_at' => 'datetime',
        'estimated_minutes' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'exit_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function location()
    {
        return $this->belongsTo(WorkshopAssemblyLocation::class, 'workshop_assembly_location_id');
    }

    public function responsibleTechnician()
    {
        return $this->belongsTo(Person::class, 'responsible_technician_person_id');
    }

    public function getActualRepairMinutesAttribute(): ?int
    {
        if (!$this->started_at || !$this->finished_at) {
            return null;
        }

        return max(0, $this->started_at->diffInMinutes($this->finished_at));
    }

    public function getEstimatedVsRealMinutesAttribute(): ?int
    {
        $actual = $this->actual_repair_minutes;
        if ($actual === null) {
            return null;
        }

        return $actual - (int) $this->estimated_minutes;
    }
}
