<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'description',
        'abbreviation',
        'image',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'category_branch')
            ->withPivot(['id', 'menu_type', 'status', 'deleted_at'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        if (!$branchId) {
            return $query;
        }

        return $query->whereHas('branches', function (Builder $branchQuery) use ($branchId) {
            $branchQuery->where('branches.id', $branchId);
        });
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
