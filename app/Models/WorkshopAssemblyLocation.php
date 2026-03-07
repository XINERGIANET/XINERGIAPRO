<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopAssemblyLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'address',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function assemblies()
    {
        return $this->hasMany(WorkshopAssembly::class, 'workshop_assembly_location_id');
    }
}
