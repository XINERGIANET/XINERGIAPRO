<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'yield_unit_id',
        'preparation_time',
        'preparation_method',
        'yield_quantity',
        'cost_total',
        'status',
        'image',
        'notes',
    ];

    protected $casts = [
        'preparation_time' => 'integer',
        'yield_quantity' => 'decimal:2',
        'cost_total' => 'decimal:2',
        'status' => 'string',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function yieldUnit()
    {
        return $this->belongsTo(Unit::class, 'yield_unit_id');
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('order');
    }

    public function isActive(): bool
    {
        return $this->status === 'A';
    }
}
