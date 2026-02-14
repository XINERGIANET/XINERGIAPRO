<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_movement_id',
        'user_id',
        'event',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function workshopMovement()
    {
        return $this->belongsTo(WorkshopMovement::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

