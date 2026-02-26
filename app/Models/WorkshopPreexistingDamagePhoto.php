<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopPreexistingDamagePhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_preexisting_damage_id',
        'photo_path',
    ];

    public function damage()
    {
        return $this->belongsTo(WorkshopPreexistingDamage::class, 'workshop_preexisting_damage_id');
    }
}

