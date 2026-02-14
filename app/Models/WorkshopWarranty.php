<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopWarranty extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_movement_id',
        'workshop_movement_detail_id',
        'starts_at',
        'ends_at',
        'status',
        'note',
    ];

    protected $casts = [
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function detail()
    {
        return $this->belongsTo(WorkshopMovementDetail::class, 'workshop_movement_detail_id');
    }
}
