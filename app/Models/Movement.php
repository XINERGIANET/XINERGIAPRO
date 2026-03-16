<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Movement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'moved_at',
        'user_id',
        'user_name',
        'person_id',
        'person_name',
        'responsible_id',
        'responsible_name',
        'comment',
        'reason',
        'status',
        'movement_type_id',
        'document_type_id',
        'branch_id',
        'parent_movement_id',
    ];

    protected $casts = [
        'moved_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function movementType()
    {
        return $this->belongsTo(MovementType::class);
    }
    public function movement()
    {
        return $this->belongsTo(Movement::class, 'parent_movement_id');
    }

    public function parentMovement()
    {
        return $this->belongsTo(Movement::class, 'parent_movement_id');
    }


    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }  
    public function cashMovement()
    {
        return $this->hasOne(CashMovements::class, 'movement_id');
    }

    public function salesMovement()
    {
        return $this->hasOne(SalesMovement::class, 'movement_id');
    }

    public function purchaseMovement()
    {
        return $this->hasOne(PurchaseMovement::class, 'movement_id');
    }

    public function warehouseMovement()
    {
        return $this->hasOne(WarehouseMovement::class, 'movement_id');
    }

    public function orderMovement()
    {
        return $this->hasOne(OrderMovement::class, 'movement_id');
    }

    public function workshopMovement()
    {
        return $this->hasOne(WorkshopMovement::class, 'movement_id');
    }

    public function isSalesInvoice(): bool
    {
        $name = Str::lower((string) ($this->documentType?->name ?? ''));

        return Str::contains($name, 'factura');
    }

    public function salesBillingStatus(): string
    {
        $status = strtoupper((string) ($this->salesMovement?->billing_status ?? ''));

        if ($status !== '') {
            return $status;
        }

        return $this->isSalesInvoice() ? 'INVOICED' : 'NOT_APPLICABLE';
    }

    public function salesBillingStatusLabel(): string
    {
        return match ($this->salesBillingStatus()) {
            'PENDING' => 'POR FACTURAR',
            'INVOICED' => 'FACTURADO',
            default => 'NO APLICA',
        };
    }

    public function salesDocumentSeries(): string
    {
        $series = trim((string) ($this->salesMovement?->series ?? ''));

        return $series !== '' ? $series : '001';
    }

    public function salesDocumentNumber(): string
    {
        if ($this->isSalesInvoice()) {
            if ($this->salesBillingStatus() === 'PENDING') {
                return '';
            }

            $billingNumber = trim((string) ($this->salesMovement?->billing_number ?? ''));
            if ($billingNumber !== '') {
                return $billingNumber;
            }
        }

        return trim((string) ($this->number ?? ''));
    }

    public function salesDocumentCode(): string
    {
        if ($this->isSalesInvoice() && $this->salesBillingStatus() === 'PENDING') {
            return 'POR FACTURAR';
        }

        $documentName = (string) ($this->documentType?->name ?? '');
        $abbr = match (true) {
            Str::contains(Str::lower($documentName), 'boleta') => 'B',
            Str::contains(Str::lower($documentName), 'factura') => 'F',
            Str::contains(Str::lower($documentName), 'ticket') => 'T',
            Str::contains(Str::lower($documentName), 'nota') => 'N',
            default => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $documentName) ?: 'X', 0, 1)),
        };

        $series = $this->salesDocumentSeries();
        $number = $this->salesDocumentNumber();

        return trim($abbr . $series . ($number !== '' ? '-' . $number : ''), '-');
    }
}
