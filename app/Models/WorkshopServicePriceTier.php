<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopServicePriceTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_service_id',
        'max_cc',
        'price',
        'order_num',
    ];

    protected $casts = [
        'max_cc' => 'integer',
        'price' => 'decimal:6',
        'order_num' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(WorkshopService::class, 'workshop_service_id');
    }
}
