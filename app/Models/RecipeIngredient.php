<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    protected $table = 'recipe_ingredients';

    protected $fillable = [
        'recipe_id',
        'product_id',
        'unit_id',
        'quantity',
        'notes',
        'unit_cost',
        'total_cost',
        'order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'order' => 'integer',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function getTotalCostAttribute()
    {
        if (array_key_exists('total_cost', $this->attributes)) {
            return (float) $this->attributes['total_cost'];
        }

        return (float) $this->quantity * (float) ($this->unit_cost ?? 0);
    }
}
