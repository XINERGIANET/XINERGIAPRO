<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopPurchaseRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'movement_id',
        'company_id',
        'branch_id',
        'supplier_person_id',
        'document_kind',
        'series',
        'document_number',
        'currency',
        'igv_rate',
        'subtotal',
        'igv',
        'total',
        'issued_at',
    ];

    protected $casts = [
        'igv_rate' => 'decimal:4',
        'subtotal' => 'decimal:6',
        'igv' => 'decimal:6',
        'total' => 'decimal:6',
        'issued_at' => 'date',
    ];

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Person::class, 'supplier_person_id');
    }
}

