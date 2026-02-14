<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'type',
        'base_price',
        'estimated_minutes',
        'active',
    ];

    protected $casts = [
        'base_price' => 'decimal:6',
        'active' => 'boolean',
    ];

    public function details()
    {
        return $this->hasMany(WorkshopMovementDetail::class, 'service_id');
    }
}

