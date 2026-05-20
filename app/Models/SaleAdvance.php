<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleAdvance extends Model
{
    protected $fillable = [
        'final_movement_id',
        'advance_movement_id',
        'applied_amount',
    ];

    public function finalMovement()
    {
        return $this->belongsTo(Movement::class, 'final_movement_id');
    }

    public function advanceMovement()
    {
        return $this->belongsTo(Movement::class, 'advance_movement_id');
    }
}
