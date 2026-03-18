<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopServiceFrequency extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_service_frequencies';

    protected $fillable = [
        'workshop_service_id',
        'km',
        'multiplier',
        'order_num',
    ];

    protected $casts = [
        'multiplier' => 'decimal:6',
        'km' => 'integer',
        'order_num' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(WorkshopService::class, 'workshop_service_id');
    }
}

