<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopServiceDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'workshop_service_details';

    protected $fillable = [
        'workshop_service_id',
        'description',
        'order_num',
    ];

    protected $casts = [
        'order_num' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(WorkshopService::class, 'workshop_service_id');
    }
}
