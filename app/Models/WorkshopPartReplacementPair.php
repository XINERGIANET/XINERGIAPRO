<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopPartReplacementPair extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'old_part_name',
        'new_part_name',
        'old_part_notes',
        'new_part_notes',
        'sort_order',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function photos()
    {
        return $this->hasMany(WorkshopPartReplacementPhoto::class)->orderBy('sort_order')->orderBy('id');
    }

    public function oldPhotos()
    {
        return $this->photos()->where('photo_type', 'old');
    }

    public function newPhotos()
    {
        return $this->photos()->where('photo_type', 'new');
    }
}
