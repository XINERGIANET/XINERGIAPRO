<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopPartReplacementPhoto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workshop_part_replacement_pair_id',
        'photo_type',
        'photo_path',
        'caption',
        'sort_order',
    ];

    public function pair()
    {
        return $this->belongsTo(WorkshopPartReplacementPair::class, 'workshop_part_replacement_pair_id');
    }
}
