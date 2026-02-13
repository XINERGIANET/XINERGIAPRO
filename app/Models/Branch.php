<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ruc',
        'company_id',
        'legal_name',
        'logo',
        'address',
        'location_id',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function people()
    {
        return $this->hasMany(Person::class);
    }

    public function views(): BelongsToMany
    {
        return $this->belongsToMany(View::class, 'view_branch')
            ->withTimestamps()
            ->withPivot('deleted_at');
    }
}
