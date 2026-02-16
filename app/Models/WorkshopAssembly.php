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
        'brand_company',
        'vehicle_type',
        'model',
        'displacement',
        'color',
        'vin',
        'quantity',
        'unit_cost',
        'total_cost',
        'assembled_at',
        'entry_at',
        'started_at',
        'finished_at',
        'exit_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:6',
        'assembled_at' => 'date',
        'entry_at' => 'datetime',
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
}

