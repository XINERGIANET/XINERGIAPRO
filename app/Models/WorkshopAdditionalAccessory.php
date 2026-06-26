<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopAdditionalAccessory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'order_num',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
