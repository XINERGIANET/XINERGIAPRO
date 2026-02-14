<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_movement_id',
        'from_status',
        'to_status',
        'user_id',
        'note',
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
