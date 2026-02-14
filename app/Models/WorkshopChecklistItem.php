<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopChecklistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'checklist_id',
        'group',
        'label',
        'result',
        'action',
        'observation',
        'order_num',
    ];

    public function checklist()
    {
        return $this->belongsTo(WorkshopChecklist::class, 'checklist_id');
    }
}

