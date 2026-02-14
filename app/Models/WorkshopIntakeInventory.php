<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopIntakeInventory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'item_key',
        'present',
    ];

    protected $casts = [
        'present' => 'boolean',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }
}

