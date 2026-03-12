<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'branch_id',
        'name',
        'type',
        'base_price',
        'estimated_minutes',
        'active',
    ];

    protected $casts = [
        'base_price' => 'decimal:6',
        'active' => 'boolean',
    ];

    public function priceTiers()
    {
        return $this->hasMany(WorkshopServicePriceTier::class, 'workshop_service_id')
            ->orderBy('max_cc')
            ->orderBy('order_num');
    }

    public function resolvePriceForDisplacement(?int $engineDisplacementCc = null): float
    {
        $tiers = $this->relationLoaded('priceTiers')
            ? $this->priceTiers
            : $this->priceTiers()->get();

        if ($tiers->isEmpty()) {
            return (float) $this->base_price;
        }

        $cc = (int) ($engineDisplacementCc ?? 0);
        if ($cc > 0) {
            $matched = $tiers->first(fn ($tier) => $cc <= (int) $tier->max_cc);
            if ($matched) {
                return (float) $matched->price;
            }

            return (float) $tiers->last()->price;
        }

        if ((float) $this->base_price > 0) {
            return (float) $this->base_price;
        }

        return (float) $tiers->first()->price;
    }

    public function details()
    {
        return $this->hasMany(WorkshopMovementDetail::class, 'service_id');
    }
}

