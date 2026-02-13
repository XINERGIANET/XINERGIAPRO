<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CashShiftRelation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'started_at',
        'ended_at',
        'status',
        'cash_movement_start_id',
        'cash_movement_end_id',
        'branch_id',
    ];

    public function cashMovementStart()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_start_id');
    }

    public function cashMovementEnd()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_end_id');
    }
}
