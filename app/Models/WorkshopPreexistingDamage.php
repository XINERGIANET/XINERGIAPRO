<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopPreexistingDamage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'side',
        'description',
        'severity',
        'photo_path',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }
}

