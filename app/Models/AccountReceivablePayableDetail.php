<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountReceivablePayableDetail extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'account_receivable_payable_details';

    protected $fillable = [
        'account_receivable_payable_id',
        'cash_movement_id',
        'situation',
        'branch_id',
    ];

    public function account()
    {
        return $this->belongsTo(AccountReceivablePayable::class, 'account_receivable_payable_id');
    }

    public function cashMovement()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
