<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopChecklist extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'type',
        'version',
        'created_by',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function items()
    {
        return $this->hasMany(WorkshopChecklistItem::class, 'checklist_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

