<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivablePayable extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'account_receivable_payables';

    protected $fillable = [
        'number',
        'cash_movement_id',
        'type',
        'status',
        'date',
        'due_date',
        'paid_at',
        'currency',
        'exchange_rate',
        'total_paid',
        'situation',
        'branch_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
        'exchange_rate' => 'decimal:3',
        'total_paid' => 'decimal:2',
    ];

    public function cashMovement()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_id');
    }

    public function details()
    {
        return $this->hasMany(AccountReceivablePayableDetail::class, 'account_receivable_payable_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
