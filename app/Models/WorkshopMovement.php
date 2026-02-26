<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkshopMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'movement_id',
        'previous_workshop_movement_id',
        'company_id',
        'branch_id',
        'vehicle_id',
        'client_person_id',
        'appointment_id',
        'intake_date',
        'delivery_date',
        'mileage_in',
        'mileage_out',
        'tow_in',
        'diagnosis_text',
        'observations',
        'intake_client_signature_path',
        'status',
        'approval_status',
        'approved_at',
        'approved_by',
        'approval_note',
        'sales_movement_id',
        'cash_movement_id',
        'subtotal',
        'tax',
        'total',
        'paid_total',
        'payment_status',
        'started_at',
        'finished_at',
        'locked_at',
    ];

    protected $casts = [
        'intake_date' => 'datetime',
        'delivery_date' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'locked_at' => 'datetime',
        'approved_at' => 'datetime',
        'tow_in' => 'boolean',
        'subtotal' => 'decimal:6',
        'tax' => 'decimal:6',
        'total' => 'decimal:6',
        'paid_total' => 'decimal:6',
    ];

    public function movement()
    {
        return $this->belongsTo(Movement::class);
    }

    public function previousWorkshopMovement()
    {
        return $this->belongsTo(self::class, 'previous_workshop_movement_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function client()
    {
        return $this->belongsTo(Person::class, 'client_person_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sale()
    {
        return $this->belongsTo(SalesMovement::class, 'sales_movement_id');
    }

    public function cash()
    {
        return $this->belongsTo(CashMovements::class, 'cash_movement_id');
    }

    public function details()
    {
        return $this->hasMany(WorkshopMovementDetail::class);
    }

    public function checklists()
    {
        return $this->hasMany(WorkshopChecklist::class);
    }

    public function damages()
    {
        return $this->hasMany(WorkshopPreexistingDamage::class);
    }

    public function intakeInventory()
    {
        return $this->hasMany(WorkshopIntakeInventory::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(WorkshopStatusHistory::class);
    }

    public function technicians()
    {
        return $this->hasMany(WorkshopMovementTechnician::class);
    }

    public function warranties()
    {
        return $this->hasMany(WorkshopWarranty::class);
    }

    public function audits()
    {
        return $this->hasMany(WorkshopAudit::class);
    }

    public function getDebtAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->paid_total);
    }
}

