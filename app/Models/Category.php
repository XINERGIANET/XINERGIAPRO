<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    public static function syncExistingToAllBranches(): void
    {
        $categoryIds = static::query()->pluck('id');
        $branchIds = DB::table('branches')->pluck('id');

        if ($categoryIds->isEmpty() || $branchIds->isEmpty()) {
            return;
        }

        $existingKeys = DB::table('category_branch')
            ->whereNull('deleted_at')
            ->select('category_id', 'branch_id')
            ->get()
            ->mapWithKeys(fn ($row) => [((int) $row->category_id) . ':' . ((int) $row->branch_id) => true]);

        $trashedByKey = DB::table('category_branch')
            ->whereNotNull('deleted_at')
            ->select('id', 'category_id', 'branch_id')
            ->get()
            ->mapWithKeys(fn ($row) => [((int) $row->category_id) . ':' . ((int) $row->branch_id) => $row->id]);

        $timestamp = now();
        $inserts = [];

        foreach ($categoryIds as $categoryId) {
            foreach ($branchIds as $branchId) {
                $key = ((int) $categoryId) . ':' . ((int) $branchId);
                if (isset($existingKeys[$key])) {
                    continue;
                }

                if (isset($trashedByKey[$key])) {
                    DB::table('category_branch')
                        ->where('id', $trashedByKey[$key])
                        ->update([
                            'deleted_at' => null,
                            'updated_at' => $timestamp,
                        ]);
                    continue;
                }

                $inserts[] = [
                    'menu_type' => 'GENERAL',
                    'status' => 'E',
                    'category_id' => (int) $categoryId,
                    'branch_id' => (int) $branchId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        if (!empty($inserts)) {
            DB::table('category_branch')->insert($inserts);
        }
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
