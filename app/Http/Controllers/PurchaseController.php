<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Branch;
use App\Models\Category;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\CashShiftRelation;
use App\Models\DocumentType;
use App\Models\DigitalWallet;
use App\Models\Location;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\Operation;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\Product;
use App\Models\ProductBranch;
use App\Models\ProductType;
use App\Models\PurchaseMovement;
use App\Models\PurchaseMovementDetail;
use App\Models\WorkshopMovement;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Services\AccountReceivablePayableService;
use App\Services\KardexSyncService;
use App\Support\PurchaseExcelImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $search = (string) $request->input('search', '');
        $dateFrom = (string) $request->input('date_from', '');
        $dateTo = (string) $request->input('date_to', '');
        $paymentType = strtoupper((string) $request->input('payment_type', ''));
        $viewId = $request->input('view_id');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        if (!in_array($paymentType, ['', 'CONTADO', 'CREDITO'], true)) {
            $paymentType = '';
        }

        $branchId = (int) session('branch_id');
        $movementType = $this->resolvePurchaseMovementType();

        $operaciones = $this->resolveOperations($viewId, $branchId);

        $purchasesBaseQuery = Movement::query()
            ->with([
                'person',
                'movementType',
                'documentType',
                'purchaseMovement.details.product',
                'purchaseMovement.details.unit',
            ])
            ->where('movement_type_id', $movementType->id)
            ->where('movements.branch_id', $branchId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('movements.number', 'ILIKE', "%{$search}%")
                        ->orWhere('movements.person_name', 'ILIKE', "%{$search}%")
                        ->orWhere('movements.user_name', 'ILIKE', "%{$search}%")
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->selectRaw('1')
                                ->from('people')
                                ->whereColumn('people.id', 'movements.person_id')
                                ->where(function ($p) use ($search) {
                                    $p->where('people.document_number', 'ILIKE', "%{$search}%")
                                      ->orWhere('people.first_name', 'ILIKE', "%{$search}%")
                                      ->orWhere('people.last_name', 'ILIKE', "%{$search}%");
                                });
                        });
                });
            })
            ->when($dateFrom !== '', function ($query) use ($dateFrom) {
                $query->whereDate('moved_at', '>=', $dateFrom);
            })
            ->when($dateTo !== '', function ($query) use ($dateTo) {
                $query->whereDate('moved_at', '<=', $dateTo);
            })
            ->when($paymentType !== '', function ($query) use ($paymentType) {
                $query->whereHas('purchaseMovement', function ($purchaseQuery) use ($paymentType) {
                    $purchaseQuery->where('payment_type', $paymentType);
                });
            });

        $purchasesTotalAmount = (float) $purchasesBaseQuery->clone()
            ->join('purchase_movements', 'purchase_movements.movement_id', '=', 'movements.id')
            ->sum('purchase_movements.total');

        $purchases = $purchasesBaseQuery
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'paymentType' => $paymentType,
            'perPage' => $perPage,
            'viewId' => $viewId,
            'operaciones' => $operaciones,
            'purchasesTotalAmount' => $purchasesTotalAmount,
        ]);
    }

    public function create(Request $request)
    {
        return view('purchases.create', $this->getFormData($request));
    }

    public function storeProviderQuick(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->with('location.parent.parent')->findOrFail($branchId);
        $companyId = (int) ($branch->company_id ?? 0);

        $validated = $request->validate([
            'person_type' => ['required', 'in:DNI,RUC,CARNET DE EXTRANGERIA,PASAPORTE'],
            'document_number' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required_unless:person_type,RUC', 'nullable', 'string', 'max:255'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'genero' => ['nullable', 'string', 'max:30'],
            'fecha_nacimiento' => ['nullable', 'date'],
        ]);

        $branchDistrictId = (int) ($branch->location_id ?? 0);
        if ($branchDistrictId <= 0) {
            return response()->json([
                'message' => 'La sucursal no tiene distrito configurado.',
            ], 422);
        }

        $existingPerson = Person::query()
            ->join('branches', 'branches.id', '=', 'people.branch_id')
            ->select('people.*')
            ->where('branches.company_id', $companyId)
            ->where('people.document_number', (string) $validated['document_number'])
            ->whereNull('people.deleted_at')
            ->first();

        if ($existingPerson) {
            $existingPerson->forceFill([
                'credit_days' => (int) ($validated['credit_days'] ?? $existingPerson->credit_days ?? 0),
                'location_id' => (int) ($validated['location_id'] ?? $existingPerson->location_id ?? $branchDistrictId),
            ])->save();

            $existingPerson->roles()->syncWithoutDetaching([
                4 => ['branch_id' => $branchId],
            ]);

            return response()->json([
                'id' => (int) $existingPerson->id,
                'person_type' => (string) $existingPerson->person_type,
                'document_number' => (string) $existingPerson->document_number,
                'first_name' => (string) $existingPerson->first_name,
                'last_name' => (string) ($existingPerson->last_name ?? ''),
                'name' => trim(((string) $existingPerson->first_name) . ' ' . ((string) $existingPerson->last_name)),
                'label' => trim(((string) $existingPerson->first_name) . ' ' . ((string) $existingPerson->last_name)),
                'document' => (string) ($existingPerson->document_number ?? ''),
                'credit_days' => (int) ($existingPerson->credit_days ?? 0),
            ]);
        }

        $validated['phone'] = (string) ($validated['phone'] ?? '');
        $validated['email'] = (string) ($validated['email'] ?? '');
        $validated['address'] = trim((string) ($validated['address'] ?? '')) ?: '-';
        $validated['location_id'] = (int) ($validated['location_id'] ?? $branchDistrictId);
        $validated['credit_days'] = (int) ($validated['credit_days'] ?? 0);
        if (strtoupper((string) ($validated['person_type'] ?? '')) === 'RUC') {
            $validated['last_name'] = '';
            $validated['genero'] = null;
        }

        $person = DB::transaction(function () use ($validated, $branchId) {
            $person = Person::query()->create(array_merge(
                $validated,
                ['branch_id' => $branchId]
            ));

            $person->roles()->syncWithoutDetaching([
                4 => ['branch_id' => $branchId],
            ]);

            return $person;
        });

        return response()->json([
            'id' => (int) $person->id,
            'person_type' => (string) $person->person_type,
            'document_number' => (string) $person->document_number,
            'first_name' => (string) $person->first_name,
            'last_name' => (string) ($person->last_name ?? ''),
            'name' => trim(((string) $person->first_name) . ' ' . ((string) $person->last_name)),
            'label' => trim(((string) $person->first_name) . ' ' . ((string) $person->last_name)),
            'document' => (string) ($person->document_number ?? ''),
            'credit_days' => (int) ($person->credit_days ?? 0),
        ]);
    }

    public function importExcel(Request $request)
    {
        $viewId = $request->input('view_id');
        $branchId = (int) session('branch_id');

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        if ($branchId <= 0) {
            return redirect()
                ->route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'Selecciona una sucursal para importar compras.');
        }

        $uploaded = $request->file('file');
        $ext = strtolower((string) $uploaded->getClientOriginalExtension()) ?: 'xlsx';
        $storedRelative = $uploaded->storeAs(
            'temp/purchase-imports',
            Str::uuid()->toString() . '.' . $ext,
            'local'
        );

        if ($storedRelative === false) {
            return redirect()
                ->route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'No se pudo guardar el archivo temporalmente.');
        }

        $fullPath = Storage::disk('local')->path($storedRelative);

        $rows = [];
        $parseError = null;
        try {
            $rows = PurchaseExcelImport::extractRows($fullPath);
        } catch (\InvalidArgumentException $e) {
            $parseError = $e->getMessage();
        } finally {
            Storage::disk('local')->delete($storedRelative);
        }

        if ($parseError !== null) {
            return redirect()
                ->route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', $parseError);
        }

        $branch = Branch::query()->findOrFail($branchId);
        $companyId = (int) ($branch->company_id ?? 0);
        $branchDistrictId = (int) ($branch->location_id ?? 0);
        $user = $request->user();
        $movementType = $this->resolvePurchaseMovementType();
        $documentTypes = DocumentType::query()
            ->where('movement_type_id', $movementType->id)
            ->get(['id', 'name']);
        $defaultDocType = $documentTypes->first();
        $defaultUnit = Unit::query()->orderBy('id')->first();

        $imported = 0;
        $skipped = 0;
        $rowErrors = [];
        $duplicates = [];
        $seenKeys = [];

        // Facturas → caja "Motolab", anything else → caja "Tivo"
        $importCashRegMotolab = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('number', 'ILIKE', '%motolab%')
            ->first();
        $importCashRegTivo = CashRegister::query()
            ->where('branch_id', $branchId)
            ->where('number', 'ILIKE', '%tivo%')
            ->first();
        $importAllPaymentMethods = PaymentMethod::query()->where('status', true)->get(['id', 'description']);
        $importShift = Shift::query()->where('branch_id', $branchId)->first() ?? Shift::query()->first();
        try {
            $importCashMvtTypeId   = $this->resolveCashMovementTypeId();
            $importCashDocTypeId   = $this->resolveCashExpenseDocumentTypeId($importCashMvtTypeId);
            $importPaymentConcept  = $this->resolvePurchasePaymentConcept();
        } catch (\RuntimeException) {
            $importCashMvtTypeId  = null;
            $importCashDocTypeId  = null;
            $importPaymentConcept = null;
        }

        // Apertura/cierre concepts and efectivo method for per-month shift sessions
        $importAperturaConcept = null;
        $importCierreConcept   = null;
        $importIngresoDocTypeId = null;
        $importEfectivoMethod  = null;
        if ($importCashMvtTypeId !== null) {
            $importAperturaConcept = PaymentConcept::query()
                ->where('type', 'I')
                ->whereRaw("LOWER(description) LIKE '%apertura%'")
                ->orderBy('id')
                ->first();
            $importCierreConcept = PaymentConcept::query()
                ->where('type', 'E')
                ->whereRaw("LOWER(description) LIKE '%cierre%'")
                ->orderBy('id')
                ->first();
            $importIngresoDocTypeId = DocumentType::query()
                ->where('movement_type_id', $importCashMvtTypeId)
                ->whereRaw("LOWER(name) = 'ingreso'")
                ->value('id');
            $importEfectivoMethod = PaymentMethod::query()
                ->where('status', true)
                ->where(fn ($q) => $q->whereRaw("LOWER(description) LIKE '%efectivo%'")
                    ->orWhereRaw("LOWER(description) LIKE '%cash%'"))
                ->orderBy('order_num')
                ->first();
        }

        $currentYearMonth = \Carbon\Carbon::now()->format('Y-m');

        // Pre-scan rows to collect unique (year-month, cash_register) combos
        $importMonthGroups = [];
        foreach ($rows as $row) {
            if ($row['date'] === '') {
                continue;
            }
            $monthKey    = substr($row['date'], 0, 7);
            $isFacturaGs = str_contains(strtolower(trim($row['doc_type_name'])), 'factura');
            $cashRegGs   = $isFacturaGs ? $importCashRegMotolab : $importCashRegTivo;
            if ($cashRegGs === null) {
                continue;
            }
            $groupKey = $monthKey . '|' . $cashRegGs->id;
            if (!isset($importMonthGroups[$groupKey])) {
                $importMonthGroups[$groupKey] = ['month' => $monthKey, 'cash_register' => $cashRegGs];
            }
        }

        try {
            DB::transaction(function () use (
                $rows, $branchId, $companyId, $branchDistrictId, $user,
                $movementType, $documentTypes, $defaultDocType, $defaultUnit,
                $importCashRegMotolab, $importCashRegTivo,
                $importAllPaymentMethods, $importShift,
                $importCashMvtTypeId, $importCashDocTypeId, $importPaymentConcept,
                $importAperturaConcept, $importCierreConcept,
                $importIngresoDocTypeId, $importEfectivoMethod, $importMonthGroups,
                &$imported, &$skipped, &$rowErrors, &$duplicates, &$seenKeys
            ) {
                $responsibleName = $user?->person
                    ? trim((string) (($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')))
                    : ($user?->name ?? 'Sistema');

                // FASE 1: Apertura histórica por mes y caja — crea CashShiftRelation (status='1')
                $importShiftRelations = [];
                if (
                    $importAperturaConcept !== null
                    && $importIngresoDocTypeId !== null
                    && $importCashMvtTypeId !== null
                    && $importShift !== null
                    && $user?->id !== null
                ) {
                    foreach ($importMonthGroups as $groupKey => $groupData) {
                        $monthKey = $groupData['month'];
                        $cashReg  = $groupData['cash_register'];
                        $firstDay = \Carbon\Carbon::parse($monthKey . '-01')->startOfDay();

                        // Si ya existe una sesión para este mes y esta caja, no crear otra
                        $alreadyExists = CashShiftRelation::query()
                            ->where('branch_id', $branchId)
                            ->whereYear('started_at', substr($monthKey, 0, 4))
                            ->whereMonth('started_at', (int) substr($monthKey, 5, 2))
                            ->whereHas('cashMovementStart', fn ($q) => $q->where('cash_register_id', $cashReg->id))
                            ->exists();

                        if ($alreadyExists) {
                            continue;
                        }

                        $aperturaMvt = Movement::query()->create([
                            'number'           => $this->generateCashMovementNumber($branchId, $cashReg->id, (int) $importAperturaConcept->id),
                            'moved_at'         => $firstDay,
                            'user_id'          => $user->id,
                            'user_name'        => $user->name ?? 'Sistema',
                            'person_id'        => null,
                            'person_name'      => $user->name ?? 'Sistema',
                            'responsible_id'   => $user->id,
                            'responsible_name' => $responsibleName,
                            'comment'          => 'Apertura caja importación ' . $monthKey,
                            'status'           => '1',
                            'movement_type_id' => $importCashMvtTypeId,
                            'document_type_id' => $importIngresoDocTypeId,
                            'branch_id'        => $branchId,
                        ]);

                        $aperturaCashMvt = CashMovements::query()->create([
                            'payment_concept_id'  => $importAperturaConcept->id,
                            'currency'            => 'PEN',
                            'exchange_rate'       => 1.0,
                            'total'               => 0.0,
                            'cash_register_id'    => $cashReg->id,
                            'cash_register'       => $cashReg->number ?? 'Caja',
                            'shift_id'            => $importShift->id,
                            'shift_snapshot'      => [
                                'name'       => $importShift->name,
                                'start_time' => $importShift->start_time,
                                'end_time'   => $importShift->end_time,
                            ],
                            'movement_id'         => $aperturaMvt->id,
                            'branch_id'           => $branchId,
                            'is_historical_import' => $monthKey < $currentYearMonth,
                        ]);

                        if ($importEfectivoMethod !== null) {
                            DB::table('cash_movement_details')->insert([
                                'cash_movement_id'   => $aperturaCashMvt->id,
                                'type'               => 'PAGADO',
                                'paid_at'            => $firstDay,
                                'payment_method_id'  => $importEfectivoMethod->id,
                                'payment_method'     => $importEfectivoMethod->description ?? 'Efectivo',
                                'number'             => $aperturaMvt->number,
                                'card_id'            => null,
                                'card'               => null,
                                'bank_id'            => null,
                                'bank'               => null,
                                'digital_wallet_id'  => null,
                                'digital_wallet'     => null,
                                'payment_gateway_id' => null,
                                'payment_gateway'    => null,
                                'amount'             => 0.0,
                                'comment'            => 'Apertura importación ' . $monthKey,
                                'status'             => 'A',
                                'branch_id'          => $branchId,
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]);
                        }

                        $shiftRelation = CashShiftRelation::create([
                            'started_at'             => $firstDay,
                            'status'                 => '1',
                            'cash_movement_start_id' => $aperturaCashMvt->id,
                            'branch_id'              => $branchId,
                        ]);

                        $importShiftRelations[$groupKey] = [
                            'relation'      => $shiftRelation,
                            'month'         => $monthKey,
                            'cash_register' => $cashReg,
                        ];
                    }
                }

                foreach ($rows as $idx => $row) {
                    $rowNum = $idx + 2;

                    if ($row['date'] === '') {
                        $rowErrors[] = "Fila {$rowNum}: fecha inválida o vacía.";
                        $skipped++;
                        continue;
                    }

                    // Resolve document type by name match
                    $docTypeName = strtoupper(trim($row['doc_type_name']));
                    $documentType = $documentTypes->first(fn ($dt) =>
                        str_contains(strtoupper((string) $dt->name), $docTypeName)
                        || ($docTypeName !== '' && str_contains($docTypeName, strtoupper((string) $dt->name)))
                    ) ?? $defaultDocType;

                    if (!$documentType) {
                        $rowErrors[] = "Fila {$rowNum}: sin tipo de comprobante disponible en el sistema.";
                        $skipped++;
                        continue;
                    }

                    // Resolve provider
                    $providerDocNumber = trim($row['provider_doc_number']);
                    // RUC/DNI de "0" o "00000000..." en el Excel significa sin documento
                    if (preg_match('/^0+$/', $providerDocNumber)) {
                        $providerDocNumber = '';
                    }
                    $providerName = trim($row['provider_name']);
                    $person = null;

                    if ($providerDocNumber !== '') {
                        $person = Person::query()
                            ->join('branches', 'branches.id', '=', 'people.branch_id')
                            ->select('people.*')
                            ->where('branches.company_id', $companyId)
                            ->where('people.document_number', $providerDocNumber)
                            ->whereNull('people.deleted_at')
                            ->first();

                        if (!$person) {
                            $provDocType = strtoupper(trim($row['provider_doc_type'] ?: 'RUC'));
                            if (!in_array($provDocType, ['DNI', 'RUC', 'CARNET DE EXTRANGERIA', 'PASAPORTE'], true)) {
                                $provDocType = 'RUC';
                            }
                            $person = Person::query()->create([
                                'person_type'     => $provDocType,
                                'document_number' => $providerDocNumber,
                                'first_name'      => $providerName !== '' ? $providerName : $providerDocNumber,
                                'last_name'       => $provDocType === 'RUC' ? '' : null,
                                'branch_id'       => $branchId,
                                'location_id'     => $branchDistrictId ?: null,
                                'credit_days'     => 0,
                                'phone'           => '',
                                'email'           => '',
                                'address'         => '-',
                            ]);
                        }

                        $person->roles()->syncWithoutDetaching([4 => ['branch_id' => $branchId]]);
                    } elseif ($providerName !== '') {
                        $person = Person::query()
                            ->where('branch_id', $branchId)
                            ->whereHas('roles', fn ($q) => $q->where('roles.id', 4)->where('role_person.branch_id', $branchId))
                            ->where(function ($q) use ($providerName) {
                                $q->where('first_name', 'ILIKE', "%{$providerName}%")
                                    ->orWhere('last_name', 'ILIKE', "%{$providerName}%");
                            })
                            ->first();

                        // Proveedor informal sin RUC/DNI: crear si no existe
                        if (!$person) {
                            $person = Person::query()->create([
                                'person_type'     => 'DNI',
                                'document_number' => '',
                                'first_name'      => $providerName,
                                'last_name'       => null,
                                'branch_id'       => $branchId,
                                'location_id'     => $branchDistrictId ?: null,
                                'credit_days'     => 0,
                                'phone'           => '',
                                'email'           => '',
                                'address'         => '-',
                            ]);
                        }

                        $person->roles()->syncWithoutDetaching([4 => ['branch_id' => $branchId]]);
                    }

                    if (!$person) {
                        $rowErrors[] = "Fila {$rowNum}: no se encontró proveedor '{$providerName}' y no tiene N° de documento.";
                        $skipped++;
                        continue;
                    }

                    // Dedup key: serie|número|ruc_o_nombre_proveedor|fecha
                    // When there's no RUC and no invoice number (informal services),
                    // use provider name + date to avoid flagging different providers as duplicates.
                    // The view prepends the document-type first letter to the series automatically
                    // (e.g. "F" + series + "-" + number), so strip any matching prefix that the
                    // Excel already includes: "F002" → "002", displayed back as "F002" ✓
                    $series = trim($row['series'] ?: '001');
                    if ($documentType) {
                        $docPrefix = strtoupper(substr((string) ($documentType->name ?? ''), 0, 1));
                        if ($docPrefix !== '' && strlen($series) > 1 && strtoupper($series[0]) === $docPrefix) {
                            $series = substr($series, 1) ?: '001';
                        }
                    }
                    $number = trim((string) $row['number']);
                    $providerKey = $providerDocNumber !== ''
                        ? $providerDocNumber
                        : strtoupper(trim($providerName));
                    $dupKey = strtoupper($series) . '|' . $number . '|' . $providerKey . '|' . $row['date'];

                    $dupInfo = [
                        'fila'       => $rowNum,
                        'serie'      => $series,
                        'numero'     => $number,
                        'proveedor'  => $providerName ?: $providerDocNumber,
                        'ruc'        => $providerDocNumber,
                        'fecha'      => $row['date'],
                        'total'      => number_format((float) $row['total'], 2),
                        'razon'      => '',
                    ];

                    // 1. Intra-archivo: misma fila repetida en el Excel
                    if (isset($seenKeys[$dupKey])) {
                        $dupInfo['razon'] = 'Duplicado dentro del mismo archivo';
                        $duplicates[] = $dupInfo;
                        $skipped++;
                        continue;
                    }

                    // 2. Contra la BD: mismo comprobante + proveedor ya registrado en la sucursal
                    $isDup = Movement::query()
                        ->where('movement_type_id', $movementType->id)
                        ->where('branch_id', $branchId)
                        ->where('person_id', $person->id)
                        ->where('number', $number)
                        ->whereHas('purchaseMovement', fn ($q) => $q->where('series', $series))
                        ->exists();

                    if ($isDup) {
                        $dupInfo['razon'] = 'Ya existe en el sistema';
                        $duplicates[] = $dupInfo;
                        $skipped++;
                        continue;
                    }

                    $seenKeys[$dupKey] = true;

                    // Resolve cash register: Factura → Motolab, else → Tivo
                    $isFactura = str_contains(strtolower($docTypeName), 'factura');
                    $rowCashReg = $isFactura ? $importCashRegMotolab : $importCashRegTivo;

                    // Match payment method from Excel's "Medio de Pago" by name
                    $paymentMethodText = trim($row['payment_method']);
                    $rowPaymentMethod  = null;
                    if ($paymentMethodText !== '' && $importAllPaymentMethods->isNotEmpty()) {
                        $needle = mb_strtolower($paymentMethodText, 'UTF-8');
                        $rowPaymentMethod = $importAllPaymentMethods->first(
                            fn ($pm) => str_contains(mb_strtolower((string) $pm->description, 'UTF-8'), $needle)
                                     || str_contains($needle, mb_strtolower((string) $pm->description, 'UTF-8'))
                        );
                    }

                    $rowAffectsCash = $rowCashReg !== null
                        && $rowPaymentMethod !== null
                        && $importShift !== null
                        && $importPaymentConcept !== null
                        && $importCashMvtTypeId !== null
                        && $importCashDocTypeId !== null;

                    // Totals from Excel (already computed)
                    $subtotal = round((float) $row['subtotal'], 2);
                    $tax      = round((float) $row['tax'], 2);
                    $total    = round((float) $row['total'], 2);
                    if ($total <= 0) {
                        $total = round($subtotal + $tax, 2);
                    }
                    if ($subtotal <= 0 && $total > 0) {
                        $subtotal = round($total - $tax, 2);
                    }

                    // Build item description from category / use / area
                    $descParts = array_filter([
                        $row['category'],
                        $row['use_description'],
                        $row['purchase_type'] ? 'Tipo: ' . $row['purchase_type'] : null,
                        $row['vehicle_type'] ? 'Veh: ' . $row['vehicle_type'] : null,
                        $row['area'] ? 'Área: ' . $row['area'] : null,
                    ]);
                    $itemDescription = implode(' | ', $descParts) ?: 'Compra importada';

                    $movement = Movement::query()->create([
                        'number'           => (string) $row['number'],
                        'moved_at'         => $row['date'],
                        'user_id'          => $user?->id,
                        'user_name'        => $user?->name ?? 'Sistema',
                        'person_id'        => $person->id,
                        'person_name'      => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                        'responsible_id'   => $user?->id,
                        'responsible_name' => $responsibleName,
                        'comment'          => $row['observations'] !== '' ? $row['observations'] : null,
                        'status'           => 'A',
                        'movement_type_id' => $movementType->id,
                        'document_type_id' => $documentType->id,
                        'branch_id'        => $branchId,
                    ]);

                    $purchase = PurchaseMovement::query()->create([
                        'series'         => $series,
                        'year'           => (string) date('Y', strtotime($row['date'])),
                        'detail_type'    => 'GLOSA',
                        'includes_tax'   => 'N',
                        'payment_type'   => 'CONTADO',
                        'affects_cash'   => $rowAffectsCash ? 'S' : 'N',
                        'currency'       => $row['currency'],
                        'exchange_rate'  => $row['exchange_rate'],
                        'subtotal'       => $subtotal,
                        'tax'            => $tax,
                        'total'          => $total,
                        'affects_kardex' => 'N',
                        'fiscal_credit'  => $row['fiscal_credit'] !== '' ? strtoupper($row['fiscal_credit']) : null,
                        'movement_id'    => $movement->id,
                        'branch_id'      => $branchId,
                    ]);

                    PurchaseMovementDetail::query()->create([
                        'detail_type'         => 'GLOSA',
                        'purchase_movement_id' => $purchase->id,
                        'code'                => 'GLOSA',
                        'description'         => mb_substr($itemDescription, 0, 255),
                        'product_id'          => null,
                        'product_json'        => null,
                        'unit_id'             => $defaultUnit?->id,
                        'tax_rate_id'         => null,
                        'quantity'            => 1,
                        'amount'              => $subtotal,
                        'comment'             => mb_substr($row['observations'], 0, 255) ?: '',
                        'status'              => 'E',
                        'branch_id'           => $branchId,
                    ]);

                    if ($rowAffectsCash) {
                        $cashOutMovement = Movement::query()->create([
                            'number'             => $this->generateCashMovementNumber($branchId, $rowCashReg->id, (int) $importPaymentConcept->id),
                            'moved_at'           => $row['date'],
                            'user_id'            => $user?->id,
                            'user_name'          => $user?->name ?? 'Sistema',
                            'person_id'          => $person->id,
                            'person_name'        => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                            'responsible_id'     => $user?->id,
                            'responsible_name'   => $responsibleName,
                            'comment'            => 'Pago compra ' . (string) $row['number'],
                            'status'             => '1',
                            'movement_type_id'   => $importCashMvtTypeId,
                            'document_type_id'   => $importCashDocTypeId,
                            'branch_id'          => $branchId,
                            'parent_movement_id' => $movement->id,
                            'shift_id'           => $importShift->id,
                            'shift_snapshot'     => [
                                'name'       => $importShift->name,
                                'start_time' => $importShift->start_time,
                                'end_time'   => $importShift->end_time,
                            ],
                        ]);

                        $rowIsPastMonth = \Carbon\Carbon::parse($row['date'])->format('Y-m') < $currentYearMonth;

                        $cashMovement = CashMovements::query()->create([
                            'payment_concept_id'  => $importPaymentConcept->id,
                            'currency'            => $row['currency'] ?: 'PEN',
                            'exchange_rate'       => $row['exchange_rate'],
                            'total'               => $total,
                            'cash_register_id'    => $rowCashReg->id,
                            'cash_register'       => $rowCashReg->number ?? 'Caja',
                            'shift_id'            => $importShift->id,
                            'shift_snapshot'      => [
                                'name'       => $importShift->name,
                                'start_time' => $importShift->start_time,
                                'end_time'   => $importShift->end_time,
                            ],
                            'movement_id'         => $cashOutMovement->id,
                            'branch_id'           => $branchId,
                            'is_historical_import' => $rowIsPastMonth,
                        ]);

                        DB::table('cash_movement_details')->insert([
                            'cash_movement_id'   => $cashMovement->id,
                            'type'               => 'PAGADO',
                            'paid_at'            => $row['date'],
                            'payment_method_id'  => $rowPaymentMethod->id,
                            'payment_method'     => $rowPaymentMethod->description ?? $paymentMethodText,
                            'number'             => $cashOutMovement->number,
                            'card_id'            => null,
                            'card'               => null,
                            'bank_id'            => null,
                            'bank'               => null,
                            'digital_wallet_id'  => null,
                            'digital_wallet'     => null,
                            'payment_gateway_id' => null,
                            'payment_gateway'    => null,
                            'amount'             => $total,
                            'comment'            => $row['observations'] !== '' ? $row['observations'] : ('Compra ' . (string) $row['number']),
                            'status'             => 'A',
                            'branch_id'          => $branchId,
                            'created_at'         => now(),
                            'updated_at'         => now(),
                        ]);
                    }

                    app(KardexSyncService::class)->syncMovement($movement);

                    $imported++;
                }

                // FASE 3: Cierre histórico — actualiza CashShiftRelation (status='0')
                if (
                    $importCierreConcept !== null
                    && $importCashDocTypeId !== null
                    && $importCashMvtTypeId !== null
                    && $importShift !== null
                    && $user?->id !== null
                ) {
                    foreach ($importShiftRelations as $groupInfo) {
                        $monthKey      = $groupInfo['month'];
                        $cashReg       = $groupInfo['cash_register'];
                        $shiftRelation = $groupInfo['relation'];
                        $lastDay       = \Carbon\Carbon::parse($monthKey . '-01')->endOfMonth()->endOfDay();

                        $cierreMvt = Movement::query()->create([
                            'number'           => $this->generateCashMovementNumber($branchId, $cashReg->id, (int) $importCierreConcept->id),
                            'moved_at'         => $lastDay,
                            'user_id'          => $user->id,
                            'user_name'        => $user->name ?? 'Sistema',
                            'person_id'        => null,
                            'person_name'      => $user->name ?? 'Sistema',
                            'responsible_id'   => $user->id,
                            'responsible_name' => $responsibleName,
                            'comment'          => 'Cierre caja importación ' . $monthKey,
                            'status'           => '1',
                            'movement_type_id' => $importCashMvtTypeId,
                            'document_type_id' => $importCashDocTypeId,
                            'branch_id'        => $branchId,
                        ]);

                        $cierreCashMvt = CashMovements::query()->create([
                            'payment_concept_id'  => $importCierreConcept->id,
                            'currency'            => 'PEN',
                            'exchange_rate'       => 1.0,
                            'total'               => 0.0,
                            'cash_register_id'    => $cashReg->id,
                            'cash_register'       => $cashReg->number ?? 'Caja',
                            'shift_id'            => $importShift->id,
                            'shift_snapshot'      => [
                                'name'       => $importShift->name,
                                'start_time' => $importShift->start_time,
                                'end_time'   => $importShift->end_time,
                            ],
                            'movement_id'         => $cierreMvt->id,
                            'branch_id'           => $branchId,
                            'is_historical_import' => $monthKey < $currentYearMonth,
                        ]);

                        if ($importEfectivoMethod !== null) {
                            DB::table('cash_movement_details')->insert([
                                'cash_movement_id'   => $cierreCashMvt->id,
                                'type'               => 'PAGADO',
                                'paid_at'            => $lastDay,
                                'payment_method_id'  => $importEfectivoMethod->id,
                                'payment_method'     => $importEfectivoMethod->description ?? 'Efectivo',
                                'number'             => $cierreMvt->number,
                                'card_id'            => null,
                                'card'               => null,
                                'bank_id'            => null,
                                'bank'               => null,
                                'digital_wallet_id'  => null,
                                'digital_wallet'     => null,
                                'payment_gateway_id' => null,
                                'payment_gateway'    => null,
                                'amount'             => 0.0,
                                'comment'            => 'Cierre importación ' . $monthKey,
                                'status'             => 'A',
                                'branch_id'          => $branchId,
                                'created_at'         => now(),
                                'updated_at'         => now(),
                            ]);
                        }

                        $shiftRelation->update([
                            'ended_at'             => $lastDay,
                            'status'               => '0',
                            'cash_movement_end_id' => $cierreCashMvt->id,
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error('importExcel compras: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return redirect()
                ->route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'Error al importar: ' . $e->getMessage());
        }

        $message = "Importación lista: {$imported} compra(s) registrada(s).";
        if (!empty($rowErrors)) {
            $shown = array_slice($rowErrors, 0, 3);
            $message .= ' Errores: ' . implode('; ', $shown);
            if (count($rowErrors) > 3) {
                $message .= ' ... y ' . (count($rowErrors) - 3) . ' más.';
            }
        }

        $redirect = redirect()
            ->route('admin.purchases.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', $message);

        if (!empty($duplicates)) {
            $redirect = $redirect->with('import_duplicates', $duplicates);
        }

        return $redirect;
    }

    public function store(Request $request)
    {
        $validated = $this->validatePurchase($request);
        $branchId = (int) session('branch_id');
        $user = $request->user();

        try {
            DB::transaction(function () use ($validated, $branchId, $user) {
                $movementType = $this->resolvePurchaseMovementType();
                $person = Person::query()
                    ->where('id', $validated['person_id'])
                    ->where('branch_id', $branchId)
                    ->whereHas('roles', function ($query) use ($branchId) {
                        $query->where('roles.id', 4)
                            ->where('role_person.branch_id', $branchId);
                    })
                    ->firstOrFail();
                $documentType = DocumentType::query()->findOrFail($validated['document_type_id']);
                $responsibleName = $user?->person
                    ? trim((string) (($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')))
                    : ($user?->name ?? 'Sistema');

                $totals = $this->calculateTotals(
                    $validated['items'],
                    (float) $validated['tax_rate_percent'],
                    $validated['includes_tax']
                );

                $movement = Movement::query()->create([
                    'number' => $validated['number'],
                    'moved_at' => $validated['moved_at'],
                    'user_id' => $user?->id,
                    'user_name' => $user?->name ?? 'Sistema',
                    'person_id' => $person->id,
                    'person_name' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                    'responsible_id' => $user?->id,
                    'responsible_name' => $responsibleName,
                    'comment' => $validated['comment'] ?? null,
                    'status' => 'A',
                    'movement_type_id' => $movementType->id,
                    'document_type_id' => $documentType->id,
                    'branch_id' => $branchId,
                ]);

                $purchase = PurchaseMovement::query()->create([
                    'series' => $validated['series'] ?? '001',
                    'year' => (string) date('Y', strtotime($validated['moved_at'])),
                    'detail_type' => $validated['detail_type'],
                    'includes_tax' => $validated['includes_tax'],
                    'payment_type' => $validated['payment_type'],
                    'affects_cash' => $validated['affects_cash'],
                    'currency' => $validated['currency'],
                    'exchange_rate' => $validated['exchange_rate'],
                    'subtotal' => $totals['subtotal'],
                    'tax' => $totals['tax'],
                    'total' => $totals['total'],
                    'affects_kardex' => $validated['affects_kardex'],
                    'movement_id' => $movement->id,
                    'branch_id' => $branchId,
                ]);

                foreach ($validated['items'] as $item) {
                    $quantity = (float) $item['quantity'];
                    $amount = (float) $item['amount'];
                    $unitId = (int) $item['unit_id'];
                    $productId = !empty($item['product_id']) ? (int) $item['product_id'] : null;
                    $product = $productId ? Product::query()->find($productId) : null;

                    PurchaseMovementDetail::query()->create([
                        'detail_type' => $validated['detail_type'],
                        'purchase_movement_id' => $purchase->id,
                        'code' => (string) ($product?->code ?? 'GLOSA'),
                        'description' => (string) ($item['description'] ?? $product?->description ?? 'Sin descripcion'),
                        'product_id' => $product?->id,
                        'product_json' => $product ? [
                            'id' => $product->id,
                            'code' => $product->code,
                            'description' => $product->description,
                        ] : null,
                        'unit_id' => $unitId,
                        'tax_rate_id' => null,
                        'quantity' => $quantity,
                        'amount' => $amount,
                        'comment' => (string) ($item['comment'] ?? ''),
                        'status' => 'E',
                        'branch_id' => $branchId,
                    ]);

                    if ($validated['affects_kardex'] === 'S' && $product) {
                        $this->incrementBranchStock($branchId, $product->id, $quantity);
                    }
                }

                app(KardexSyncService::class)->syncMovement($movement);

                if (($validated['payment_type'] ?? 'CONTADO') === 'CREDITO') {
                    $dueDate = $this->resolvePurchaseDueDate($validated, $person);
                    $this->registerPurchaseDebt(
                        movement: $movement,
                        person: $person,
                        validated: $validated,
                        total: (float) $totals['total'],
                        branchId: $branchId,
                        user: $user,
                        dueDate: $dueDate,
                    );
                } elseif (
                    ($validated['payment_type'] ?? 'CONTADO') === 'CONTADO'
                    && ($validated['affects_cash'] ?? 'N') === 'S'
                ) {
                    $this->registerPurchaseCashOutflow(
                        movement: $movement,
                        person: $person,
                        validated: $validated,
                        total: (float) $totals['total'],
                        branchId: $branchId,
                        user: $user
                    );
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'error' => $e->getMessage() ?: 'No se pudo registrar la compra.',
            ]);
        }

        return redirect()
            ->route('admin.purchases.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Compra registrada correctamente.');
    }

    public function edit(Request $request, Movement $purchase)
    {
        $this->assertValidPurchaseMovement($purchase);
        $purchase->load(['purchaseMovement.details']);

        return view('purchases.create', $this->getFormData($request, $purchase));
    }

    public function update(Request $request, Movement $purchase)
    {
        $this->assertValidPurchaseMovement($purchase);
        $purchase->load(['purchaseMovement.details']);

        $validated = $this->validatePurchase($request);
        $branchId = (int) session('branch_id');

        try {
            DB::transaction(function () use ($purchase, $validated, $branchId) {
                $documentType = DocumentType::query()->findOrFail($validated['document_type_id']);
                $person = Person::query()
                    ->where('id', $validated['person_id'])
                    ->where('branch_id', $branchId)
                    ->whereHas('roles', function ($query) use ($branchId) {
                        $query->where('roles.id', 4)
                            ->where('role_person.branch_id', $branchId);
                    })
                    ->firstOrFail();
                $user = request()->user();
                $responsibleName = $user?->person
                    ? trim((string) (($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? '')))
                    : ($user?->name ?? 'Sistema');

                $oldPurchase = $purchase->purchaseMovement;
                $oldDetails = $oldPurchase->details;

                if ($oldPurchase->affects_kardex === 'S') {
                    foreach ($oldDetails as $detail) {
                        if ($detail->product_id) {
                            $this->decrementBranchStock($branchId, (int) $detail->product_id, (float) $detail->quantity);
                        }
                    }
                }

                $totals = $this->calculateTotals(
                    $validated['items'],
                    (float) $validated['tax_rate_percent'],
                    $validated['includes_tax']
                );

                $purchase->update([
                    'number' => $validated['number'],
                    'moved_at' => $validated['moved_at'],
                    'person_id' => $person->id,
                    'person_name' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                    'responsible_id' => $user?->id,
                    'responsible_name' => $responsibleName,
                    'comment' => $validated['comment'] ?? null,
                    'document_type_id' => $documentType->id,
                ]);

                $oldPurchase->update([
                    'series' => $validated['series'] ?? '001',
                    'year' => (string) date('Y', strtotime($validated['moved_at'])),
                    'detail_type' => $validated['detail_type'],
                    'includes_tax' => $validated['includes_tax'],
                    'payment_type' => $validated['payment_type'],
                    'affects_cash' => $validated['affects_cash'],
                    'currency' => $validated['currency'],
                    'exchange_rate' => $validated['exchange_rate'],
                    'subtotal' => $totals['subtotal'],
                    'tax' => $totals['tax'],
                    'total' => $totals['total'],
                    'affects_kardex' => $validated['affects_kardex'],
                ]);

                PurchaseMovementDetail::query()
                    ->where('purchase_movement_id', $oldPurchase->id)
                    ->delete();

                foreach ($validated['items'] as $item) {
                    $quantity = (float) $item['quantity'];
                    $amount = (float) $item['amount'];
                    $unitId = (int) $item['unit_id'];
                    $productId = !empty($item['product_id']) ? (int) $item['product_id'] : null;
                    $product = $productId ? Product::query()->find($productId) : null;

                    PurchaseMovementDetail::query()->create([
                        'detail_type' => $validated['detail_type'],
                        'purchase_movement_id' => $oldPurchase->id,
                        'code' => (string) ($product?->code ?? 'GLOSA'),
                        'description' => (string) ($item['description'] ?? $product?->description ?? 'Sin descripcion'),
                        'product_id' => $product?->id,
                        'product_json' => $product ? [
                            'id' => $product->id,
                            'code' => $product->code,
                            'description' => $product->description,
                        ] : null,
                        'unit_id' => $unitId,
                        'tax_rate_id' => null,
                        'quantity' => $quantity,
                        'amount' => $amount,
                        'comment' => (string) ($item['comment'] ?? ''),
                        'status' => 'E',
                        'branch_id' => $branchId,
                    ]);

                    if ($validated['affects_kardex'] === 'S' && $product) {
                        $this->incrementBranchStock($branchId, $product->id, $quantity);
                    }
                }

                $this->deletePurchaseFinancialRecords($purchase);

                if (($validated['payment_type'] ?? 'CONTADO') === 'CREDITO') {
                    $dueDate = $this->resolvePurchaseDueDate($validated, $person);
                    $this->registerPurchaseDebt(
                        movement: $purchase,
                        person: $person,
                        validated: $validated,
                        total: (float) $totals['total'],
                        branchId: $branchId,
                        user: $user,
                        dueDate: $dueDate,
                    );
                } elseif (
                    ($validated['payment_type'] ?? 'CONTADO') === 'CONTADO'
                    && ($validated['affects_cash'] ?? 'N') === 'S'
                ) {
                    $this->registerPurchaseCashOutflow(
                        movement: $purchase,
                        person: $person,
                        validated: $validated,
                        total: (float) $totals['total'],
                        branchId: $branchId,
                        user: $user
                    );
                }

                app(KardexSyncService::class)->syncMovement($purchase);
            });
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors([
                'error' => $e->getMessage() ?: 'No se pudo actualizar la compra.',
            ]);
        }

        return redirect()
            ->route('admin.purchases.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Compra actualizada correctamente.');
    }

    public function destroy(Request $request, Movement $purchase)
    {
        $this->assertValidPurchaseMovement($purchase);
        $purchase->load(['purchaseMovement.details']);
        $branchId = (int) session('branch_id');

        DB::transaction(function () use ($purchase, $branchId) {
            $purchaseModel = $purchase->purchaseMovement;
            if ($purchaseModel->affects_kardex === 'S') {
                foreach ($purchaseModel->details as $detail) {
                    if ($detail->product_id) {
                        $this->decrementBranchStock($branchId, (int) $detail->product_id, (float) $detail->quantity);
                    }
                }
            }

            app(KardexSyncService::class)->deleteMovement($purchase->id);
            PurchaseMovementDetail::query()->where('purchase_movement_id', $purchaseModel->id)->delete();
            $purchaseModel->delete();
            $purchase->delete();
        });

        return redirect()
            ->route('admin.purchases.index', $request->filled('view_id') ? ['view_id' => $request->input('view_id')] : [])
            ->with('status', 'Compra eliminada correctamente.');
    }

    private function validatePurchase(Request $request): array
    {
        $validated = $request->validate([
            'moved_at' => ['required', 'date'],
            'person_id' => ['required', 'integer', 'exists:people,id'],
            'document_type_id' => ['required', 'integer', 'exists:document_types,id'],
            'number' => ['required', 'string', 'max:50'],
            'series' => ['nullable', 'string', 'max:20'],
            'detail_type' => ['required', Rule::in(['DETALLADO', 'GLOSA'])],
            'includes_tax' => ['required', Rule::in(['S', 'N'])],
            'payment_type' => ['required', Rule::in(['CONTADO', 'CREDITO'])],
            'due_date' => ['nullable', 'date'],
            'affects_cash' => ['required', Rule::in(['S', 'N'])],
            'affects_kardex' => ['required', Rule::in(['S', 'N'])],
            'currency' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0.001'],
            'tax_rate_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'comment' => ['nullable', 'string'],
            'cash_register_id' => ['nullable', 'integer', 'exists:cash_registers,id'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*.payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'payment_methods.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_methods.*.payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'payment_methods.*.card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'payment_methods.*.digital_wallet_id' => ['nullable', 'integer', 'exists:digital_wallets,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.comment' => ['nullable', 'string'],
        ]);

        $defaultUnitId = (int) (Unit::query()->orderBy('id')->value('id') ?? 0);

        foreach ($validated['items'] as $index => $item) {
            $isGlosa = ($validated['detail_type'] ?? 'DETALLADO') === 'GLOSA';
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.{$index}.description" => 'La descripcion del item es obligatoria.',
                ]);
            }

            if ($isGlosa) {
                $validated['items'][$index]['product_id'] = !empty($item['product_id']) ? (int) $item['product_id'] : null;
                $validated['items'][$index]['unit_id'] = !empty($item['unit_id'])
                    ? (int) $item['unit_id']
                    : ($defaultUnitId > 0 ? $defaultUnitId : null);
            } else {
                if (empty($item['product_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.product_id" => 'Debe seleccionar un producto.',
                    ]);
                }
                if (empty($item['unit_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "items.{$index}.unit_id" => 'Debe seleccionar una unidad.',
                    ]);
                }
                $validated['items'][$index]['product_id'] = (int) $item['product_id'];
                $validated['items'][$index]['unit_id'] = (int) $item['unit_id'];
            }
        }

        return $validated;
    }

    private function getFormData(Request $request, ?Movement $purchase = null): array
    {
        $branchId = (int) session('branch_id');
        $movementType = $this->resolvePurchaseMovementType();

        $people = Person::query()
            ->where('branch_id', $branchId)
            ->whereHas('roles', function ($query) use ($branchId) {
                $query->where('roles.id', 4)
                    ->where('role_person.branch_id', $branchId);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number', 'credit_days']);

        $branch = Branch::query()->with('location.parent.parent')->find($branchId);
        $district = $branch?->location;
        $province = $district?->parent;
        $department = $province?->parent;
        $locationData = $this->getLocationData((int) ($branch?->location_id ?? 0));

        $documentTypes = DocumentType::query()
            ->where('movement_type_id', $movementType->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $cashRegisters = CashRegister::query()
            ->where('branch_id', $branchId)
            ->orderByRaw("CASE WHEN status = 'A' THEN 0 ELSE 1 END")
            ->orderBy('number')
            ->get(['id', 'number', 'status']);
        $standardCashRegisterId = $cashRegisters->firstWhere('status', 'A')->id ?? $cashRegisters->first()->id ?? null;
        $invoiceCashRegisterId = $this->getBranchConfiguredCashRegisterId($branchId, $cashRegisters, 'caja factur')
            ?: $standardCashRegisterId;
        $defaultCashRegisterId = $this->isInvoiceDocumentTypeId((int) ($documentTypes->first()->id ?? 0), $documentTypes)
            ? $invoiceCashRegisterId
            : $standardCashRegisterId;

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $paymentGateways = PaymentGateways::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $cards = Card::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'type', 'icon', 'order_num']);

        $digitalWallets = DigitalWallet::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->get(['id', 'description', 'order_num']);

        $units = Unit::query()->orderBy('description')->get(['id', 'description']);

        ProductType::ensureDefaultsForBranch($branchId);
        $productQuickCreateTypes = ProductType::query()
            ->where('branch_id', $branchId)
            ->where('status', true)
            ->orderBy('name')
            ->get();
        $productQuickCreateCategories = Category::query()
            ->forBranch($branchId)
            ->orderBy('description')
            ->get();
        $productQuickCreateTaxRates = TaxRate::query()->where('status', true)->orderBy('order_num')->get();
        $productQuickCreateSuppliers = Person::query()
            ->where('branch_id', $branchId)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'document_number']);
        $productQuickCreateCurrentBranch = Branch::query()->find($branchId);
        $productQuickCreateNextCode = $this->nextBranchProductCodeForPurchase($branchId);

        $hasProductMarca = Schema::hasColumn('products', 'marca');

        $productColumns = [
            'products.id',
            'products.code',
            'products.description',
            'products.image',
            'products.base_unit_id as unit_sale',
            'product_branch.purchase_price',
            'product_branch.avg_cost',
            'product_branch.stock',
            'units.description as unit_name',
            DB::raw('CASE WHEN category_branch.id IS NOT NULL THEN categories.description ELSE NULL END as category_name'),
        ];

        if ($hasProductMarca) {
            $productColumns[] = 'products.marca';
        } else {
            $productColumns[] = DB::raw("'' as marca");
        }

        $products = Product::query()
            ->join('product_branch', function ($join) use ($branchId) {
                $join->on('product_branch.product_id', '=', 'products.id')
                    ->where('product_branch.branch_id', '=', $branchId);
            })
            ->leftJoin('units', 'units.id', '=', 'products.base_unit_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('category_branch', function ($join) use ($branchId) {
                $join->on('category_branch.category_id', '=', 'categories.id')
                    ->where('category_branch.branch_id', '=', $branchId)
                    ->whereNull('category_branch.deleted_at');
            })
            ->where('products.classification', 'GOOD')
            ->orderBy('products.description')
            ->get($productColumns);

        $defaultTaxRate = (float) (TaxRate::query()->where('status', true)->orderBy('order_num')->value('tax_rate') ?? 18);
        $purchaseNumberPreview = $documentTypes->isNotEmpty()
            ? $this->generatePurchaseNumber((int) $documentTypes->first()->id, $branchId)
            : '00000001';
        $purchaseMovement = $purchase?->purchaseMovement;
        $purchaseCashMovement = $purchase
            ? CashMovements::query()
                ->with('details')
                ->whereHas('movement', fn ($query) => $query->where('parent_movement_id', $purchase->id))
                ->latest('id')
                ->first()
            : null;
        $purchaseCashDetails = $purchaseCashMovement?->details ?? collect();
        $isEditing = $purchase !== null;
        $initialItems = old('items', $isEditing
            ? $purchaseMovement?->details->map(fn ($detail) => [
                'product_id' => $detail->product_id,
                'unit_id' => $detail->unit_id,
                'description' => (string) ($detail->description ?? ''),
                'quantity' => (float) ($detail->quantity ?? 1),
                'amount' => (float) ($detail->amount ?? 0),
                'comment' => (string) ($detail->comment ?? ''),
            ])->values()->all()
            : []);
        if ($initialItems === [] && !$isEditing) {
            $initialItems = $this->buildInitialItemsFromWorkshopQuotation(
                (int) $request->input('workshop_quotation_id', 0),
                $branchId
            );
        }
        $initialPaymentRows = old('payment_methods', $isEditing
            ? $purchaseCashDetails->map(fn ($detail) => [
                'payment_method_id' => $detail->payment_method_id,
                'amount' => (float) ($detail->amount ?? 0),
                'payment_gateway_id' => $detail->payment_gateway_id,
                'card_id' => $detail->card_id,
                'digital_wallet_id' => $detail->digital_wallet_id,
            ])->values()->all()
            : []);
        $initialTaxRate = (float) old(
            'tax_rate_percent',
            ($isEditing && (float) ($purchaseMovement?->subtotal ?? 0) > 0)
                ? round((((float) ($purchaseMovement?->tax ?? 0)) / ((float) $purchaseMovement->subtotal)) * 100, 2)
                : $defaultTaxRate
        );

        $purchaseCreateConfig = [
            'products' => $products->map(fn ($p) => [
                'id' => (int) $p->id,
                'code' => (string) ($p->code ?? ''),
                'marca' => (string) ($p->marca ?? ''),
                'name' => trim(implode(' - ', array_filter([
                    trim((string) ($p->marca ?? '')) !== '' ? (string) $p->marca : null,
                    (string) ($p->description ?? ''),
                ], fn ($v) => $v !== null && $v !== ''))),
                'img' => ($p->image && !empty($p->image))
                    ? asset('storage/' . ltrim((string) $p->image, '/'))
                    : null,
                'category' => trim((string) ($p->category_name ?? '')) !== '' ? (string) $p->category_name : 'Sin categoria',
                'stock' => (float) ($p->stock ?? 0),
                'unit_id' => (int) ($p->unit_sale ?? 0),
                'unit_name' => (string) ($p->unit_name ?? ''),
                'cost' => (float) ($p->purchase_price ?? $p->avg_cost ?? 0),
            ])->values(),
            'units' => $units,
            'providers' => $people->map(fn ($person) => [
                'id' => (int) $person->id,
                'label' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
                'document' => (string) ($person->document_number ?? ''),
                'credit_days' => (int) ($person->credit_days ?? 0),
            ])->values(),
            'documentTypes' => $documentTypes->values(),
            'cashRegisters' => $cashRegisters->values(),
            'standardCashRegisterId' => (int) ($standardCashRegisterId ?? 0),
            'invoiceCashRegisterId' => (int) ($invoiceCashRegisterId ?? 0),
            'paymentMethods' => $paymentMethods->values(),
            'paymentGateways' => $paymentGateways->values(),
            'cards' => $cards->values(),
            'digitalWallets' => $digitalWallets->values(),
            'initialProviderId' => (int) old('person_id', $purchase?->person_id ?? 0),
            'initialItems' => $initialItems,
            'initialPaymentType' => (string) old('payment_type', $purchaseMovement?->payment_type ?? 'CONTADO'),
            'initialDueDate' => (string) old('due_date', optional($purchaseCashDetails->first()?->due_at)->format('Y-m-d') ?? ''),
            'initialDocumentTypeId' => (int) old('document_type_id', $purchase?->document_type_id ?? ($documentTypes->first()->id ?? 0)),
            'initialCashRegisterId' => (int) old('cash_register_id', $purchaseCashMovement?->cash_register_id ?? ($defaultCashRegisterId ?? 0)),
            'initialRows' => $initialPaymentRows,
            'initialDetailType' => (string) old('detail_type', $purchaseMovement?->detail_type ?? 'DETALLADO'),
            'initialAffectsCash' => (string) old('affects_cash', $purchaseMovement?->affects_cash ?? 'N'),
            'initialAffectsKardex' => (string) old('affects_kardex', $purchaseMovement?->affects_kardex ?? 'S'),
            'initialTaxRate' => $initialTaxRate,
            'initialIncludesTax' => (string) old('includes_tax', $purchaseMovement?->includes_tax ?? 'S'),
            'initialCurrency' => (string) old('currency', $purchaseMovement?->currency ?? 'PEN'),
            'initialExchangeRate' => (float) old('exchange_rate', $purchaseMovement?->exchange_rate ?? 3.5),
            'initialMovedAt' => (string) old('moved_at', optional($purchase?->moved_at)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i')),
            'initialSeries' => (string) old('series', $purchaseMovement?->series ?? '001'),
            'purchaseNumberPreview' => (string) old('number', $purchase?->number ?? $purchaseNumberPreview),
            'quickProviderStoreUrl' => route('admin.purchases.providers.store'),
            'reniecApiUrl' => route('api.reniec'),
            'rucApiUrl' => route('api.ruc'),
            'departments' => $locationData['departments'],
            'provinces' => $locationData['provinces'],
            'districts' => $locationData['districts'],
            'branchDepartmentId' => $locationData['selectedDepartmentId'],
            'branchProvinceId' => $locationData['selectedProvinceId'],
            'branchDistrictId' => $locationData['selectedDistrictId'],
            'branchDepartmentName' => (string) ($department->name ?? ''),
            'branchProvinceName' => (string) ($province->name ?? ''),
            'branchDistrictName' => (string) ($district->name ?? ''),
            'isEditing' => $isEditing,
        ];

        return [
            'viewId' => $request->input('view_id'),
            'people' => $people,
            'documentTypes' => $documentTypes,
            'cashRegisters' => $cashRegisters,
            'paymentMethods' => $paymentMethods,
            'paymentGateways' => $paymentGateways,
            'cards' => $cards,
            'digitalWallets' => $digitalWallets,
            'units' => $units,
            'products' => $products,
            'defaultTaxRate' => $defaultTaxRate,
            'purchaseNumberPreview' => $purchaseNumberPreview,
            'purchaseCreateConfig' => $purchaseCreateConfig,
            'productQuickCreate' => [
                'viewId' => $request->input('view_id'),
                'productTypes' => $productQuickCreateTypes,
                'nextProductCode' => $productQuickCreateNextCode,
                'currentBranch' => $productQuickCreateCurrentBranch,
                'taxRates' => $productQuickCreateTaxRates,
                'suppliers' => $productQuickCreateSuppliers,
                'categories' => $productQuickCreateCategories,
                'units' => $units,
                'afterCreate' => 'purchase_create',
            ],
            'purchase' => $purchase,
        ];
    }

    private function nextBranchProductCodeForPurchase(int $branchId): string
    {
        if ($branchId <= 0) {
            return '1';
        }

        $lastCode = Product::query()
            ->join('product_branch', 'product_branch.product_id', '=', 'products.id')
            ->where('product_branch.branch_id', $branchId)
            ->whereNull('product_branch.deleted_at')
            ->orderByDesc('products.id')
            ->value('products.code');

        if (!$lastCode) {
            return '1';
        }

        $code = trim((string) $lastCode);

        if (preg_match('/^(.*?)(\d+)$/', $code, $matches)) {
            $prefix = $matches[1];
            $number = $matches[2];
            $next = (string) ((int) $number + 1);

            return $prefix . str_pad($next, strlen($number), '0', STR_PAD_LEFT);
        }

        if (is_numeric($code)) {
            return (string) ((int) $code + 1);
        }

        return '1';
    }

    private function resolvePurchaseMovementType(): MovementType
    {
        $movementType = MovementType::query()
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%compra%')
                    ->orWhere('description', 'ILIKE', '%purchase%');
            })
            ->orderBy('id')
            ->first();

        if (!$movementType) {
            $movementType = MovementType::query()->find(3);
        }

        if (!$movementType) {
            $movementType = MovementType::query()->orderBy('id')->firstOrFail();
        }

        return $movementType;
    }

    private function getBranchConfiguredCashRegisterId(int $branchId, $cashRegisters, string $needle): ?int
    {
        if ($branchId <= 0) {
            return $cashRegisters->firstWhere('status', 'A')->id ?? $cashRegisters->first()->id ?? null;
        }

        $configuredValue = DB::table('branch_parameters as bp')
            ->join('parameters as p', 'p.id', '=', 'bp.parameter_id')
            ->where('bp.branch_id', $branchId)
            ->whereNull('bp.deleted_at')
            ->whereNull('p.deleted_at')
            ->where('p.description', 'ILIKE', '%' . $needle . '%')
            ->orderBy('p.id')
            ->value('bp.value');

        if (is_numeric($configuredValue)) {
            $configuredId = (int) $configuredValue;
            $exists = $cashRegisters->contains(fn ($cashRegister) => (int) $cashRegister->id === $configuredId);
            if ($exists) {
                return $configuredId;
            }
        }

        return $cashRegisters->firstWhere('status', 'A')->id ?? $cashRegisters->first()->id ?? null;
    }

    private function isInvoiceDocumentTypeId(?int $documentTypeId, $documentTypes): bool
    {
        if ((int) $documentTypeId <= 0) {
            return false;
        }

        $documentType = collect($documentTypes)->first(fn ($item) => (int) ($item->id ?? 0) === (int) $documentTypeId);
        $name = mb_strtolower(trim((string) ($documentType->name ?? '')), 'UTF-8');

        return str_contains($name, 'factura');
    }

    private function getLocationData(?int $defaultLocationId = null): array
    {
        $departments = Location::query()
            ->where('type', 'department')
            ->orderBy('name')
            ->get(['id', 'name']);

        $provinces = Location::query()
            ->where('type', 'province')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_location_id']);

        $districts = Location::query()
            ->where('type', 'district')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_location_id']);

        $selectedDistrictId = $defaultLocationId ?: null;
        $selectedProvinceId = null;
        $selectedDepartmentId = null;

        if ($selectedDistrictId) {
            $district = Location::query()->find($selectedDistrictId);
            if ($district) {
                $selectedProvinceId = $district->parent_location_id;
                if ($selectedProvinceId) {
                    $province = Location::query()->find($selectedProvinceId);
                    $selectedDepartmentId = $province?->parent_location_id;
                }
            }
        }

        return [
            'departments' => $departments->values(),
            'provinces' => $provinces->values(),
            'districts' => $districts->values(),
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedProvinceId' => $selectedProvinceId,
            'selectedDistrictId' => $selectedDistrictId,
        ];
    }

    private function resolveOperations($viewId, int $branchId)
    {
        $profileId = session('profile_id') ?? auth()->user()?->profile_id;
        if (!$viewId || !$branchId || !$profileId) {
            return collect();
        }

        return Operation::query()
            ->select('operations.*')
            ->join('branch_operation', function ($join) use ($branchId) {
                $join->on('branch_operation.operation_id', '=', 'operations.id')
                    ->where('branch_operation.branch_id', $branchId)
                    ->where('branch_operation.status', 1)
                    ->whereNull('branch_operation.deleted_at');
            })
            ->join('operation_profile_branch', function ($join) use ($branchId, $profileId) {
                $join->on('operation_profile_branch.operation_id', '=', 'operations.id')
                    ->where('operation_profile_branch.branch_id', $branchId)
                    ->where('operation_profile_branch.profile_id', $profileId)
                    ->where('operation_profile_branch.status', 1)
                    ->whereNull('operation_profile_branch.deleted_at');
            })
            ->where('operations.status', 1)
            ->where('operations.view_id', $viewId)
            ->whereNull('operations.deleted_at')
            ->orderBy('operations.id')
            ->distinct()
            ->get();
    }

    private function generatePurchaseNumber(int $documentTypeId, int $branchId): string
    {
        $year = (int) now()->year;

        $query = Movement::query()
            ->where('branch_id', $branchId)
            ->where('document_type_id', $documentTypeId)
            ->whereYear('moved_at', $year)
            ->lockForUpdate();

        $lastCorrelative = 0;
        foreach ($query->pluck('number') as $number) {
            $raw = trim((string) $number);
            if ($raw !== '' && preg_match('/^\d+$/', $raw) === 1) {
                $lastCorrelative = max($lastCorrelative, (int) $raw);
            }
        }

        return str_pad((string) ($lastCorrelative + 1), 8, '0', STR_PAD_LEFT);
    }

    private function calculateTotals(array $items, float $taxRatePercent, string $includesTax): array
    {
        $lineTotal = 0.0;
        foreach ($items as $item) {
            $lineTotal += ((float) $item['quantity']) * ((float) $item['amount']);
        }

        $lineTotal = round($lineTotal, 2);
        $rate = round($taxRatePercent / 100, 6);

        if ($includesTax === 'S') {
            $subtotal = $rate > 0 ? round($lineTotal / (1 + $rate), 2) : $lineTotal;
            $tax = round($lineTotal - $subtotal, 2);
            $total = $lineTotal;
        } else {
            $subtotal = $lineTotal;
            $tax = round($subtotal * $rate, 2);
            $total = round($subtotal + $tax, 2);
        }

        return compact('subtotal', 'tax', 'total');
    }

    private function incrementBranchStock(int $branchId, int $productId, float $quantity): void
    {
        $pb = $this->ensureProductBranchRecord($branchId, $productId);

        $pb->update([
            'stock' => round(((float) $pb->stock) + $quantity, 4),
        ]);
    }

    private function decrementBranchStock(int $branchId, int $productId, float $quantity): void
    {
        $pb = $this->ensureProductBranchRecord($branchId, $productId);

        $pb->update([
            'stock' => round(((float) $pb->stock) - $quantity, 4),
        ]);
    }

    private function ensureProductBranchRecord(int $branchId, int $productId): ProductBranch
    {
        $pb = ProductBranch::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($pb) {
            return $pb;
        }

        return ProductBranch::query()->create([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'status' => 'A',
            'stock' => 0,
            'price' => 0,
            'purchase_price' => 0,
            'stock_minimum' => 0,
            'stock_maximum' => 0,
            'minimum_sell' => 0,
            'minimum_purchase' => 0,
            'favorite' => 'N',
            'tax_rate_id' => null,
            'unit_sale' => 'N',
            'duration_minutes' => null,
            'supplier_id' => null,
            'expiration_date' => null,
        ]);
    }

    private function registerPurchaseCashOutflow(
        Movement $movement,
        Person $person,
        array $validated,
        float $total,
        int $branchId,
        $user
    ): void {
        $paymentRows = collect($validated['payment_methods'] ?? [])
            ->filter(fn ($row) => !empty($row['payment_method_id']) && (float) ($row['amount'] ?? 0) > 0)
            ->values();

        if ($paymentRows->isEmpty()) {
            throw new \RuntimeException('Debe registrar al menos un metodo de pago para afectar caja.');
        }

        $paidAmount = (float) $paymentRows->sum(fn ($row) => (float) ($row['amount'] ?? 0));
        if (abs($paidAmount - $total) > 0.01) {
            throw new \RuntimeException('La suma de los metodos de pago debe coincidir con el total de la compra.');
        }

        $cashRegisterId = (int) ($validated['cash_register_id'] ?? 0);
        if ($cashRegisterId <= 0) {
            throw new \RuntimeException('Debe seleccionar una caja para registrar el pago de la compra.');
        }

        $cashRegister = CashRegister::query()
            ->where('branch_id', $branchId)
            ->findOrFail($cashRegisterId);

        $paymentConcept = $this->resolvePurchasePaymentConcept();
        $cashMovementTypeId = $this->resolveCashMovementTypeId();
        $cashDocumentTypeId = $this->resolveCashExpenseDocumentTypeId($cashMovementTypeId);
        $shift = Shift::query()->where('branch_id', $branchId)->first() ?? Shift::query()->first();

        if (!$shift) {
            throw new \RuntimeException('No hay turno disponible para registrar el pago en caja.');
        }

        $cashOutMovement = Movement::query()->create([
            'number' => $this->generateCashMovementNumber($branchId, $cashRegisterId, (int) $paymentConcept->id),
            'moved_at' => $validated['moved_at'],
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'person_id' => $person->id,
            'person_name' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->name ?? 'Sistema',
            'comment' => 'Pago de compra ' . $movement->number,
            'status' => '1',
            'movement_type_id' => $cashMovementTypeId,
            'document_type_id' => $cashDocumentTypeId,
            'branch_id' => $branchId,
            'parent_movement_id' => $movement->id,
            'shift_id' => $shift->id,
            'shift_snapshot' => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ],
        ]);

        $cashMovement = CashMovements::query()->create([
            'payment_concept_id' => $paymentConcept->id,
            'currency' => $validated['currency'],
            'exchange_rate' => (float) $validated['exchange_rate'],
            'total' => $total,
            'cash_register_id' => $cashRegisterId,
            'cash_register' => $cashRegister->number ?? 'Caja Principal',
            'shift_id' => $shift->id,
            'shift_snapshot' => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ],
            'movement_id' => $cashOutMovement->id,
            'branch_id' => $branchId,
        ]);

        foreach ($paymentRows as $paymentMethodData) {
            $paymentMethod = PaymentMethod::query()->findOrFail((int) $paymentMethodData['payment_method_id']);
            $paymentMethodDescription = mb_strtolower((string) ($paymentMethod->description ?? ''), 'UTF-8');
            $paymentGateway = !empty($paymentMethodData['payment_gateway_id'])
                ? PaymentGateways::query()->find((int) $paymentMethodData['payment_gateway_id'])
                : null;
            $card = !empty($paymentMethodData['card_id'])
                ? Card::query()->find((int) $paymentMethodData['card_id'])
                : null;
            $digitalWallet = !empty($paymentMethodData['digital_wallet_id'])
                ? DigitalWallet::query()->find((int) $paymentMethodData['digital_wallet_id'])
                : null;

            if ((str_contains($paymentMethodDescription, 'billetera') || str_contains($paymentMethodDescription, 'wallet')) && !$digitalWallet) {
                throw new \RuntimeException('Debe seleccionar la billetera digital del metodo de pago.');
            }

            if ((str_contains($paymentMethodDescription, 'tarjeta') || str_contains($paymentMethodDescription, 'card')) && !$card) {
                throw new \RuntimeException('Debe seleccionar el detalle de tarjeta del metodo de pago.');
            }

            DB::table('cash_movement_details')->insert([
                'cash_movement_id' => $cashMovement->id,
                'type' => 'PAGADO',
                'paid_at' => $validated['moved_at'],
                'payment_method_id' => $paymentMethod->id,
                'payment_method' => $paymentMethod->description ?? '',
                'number' => $cashOutMovement->number,
                'card_id' => $card?->id,
                'card' => $card?->description,
                'bank_id' => null,
                'bank' => null,
                'digital_wallet_id' => $digitalWallet?->id,
                'digital_wallet' => $digitalWallet?->description,
                'payment_gateway_id' => $paymentGateway?->id,
                'payment_gateway' => $paymentGateway?->description,
                'amount' => (float) ($paymentMethodData['amount'] ?? 0),
                'comment' => $validated['comment'] ?? ('Pago de compra ' . $movement->number),
                'status' => 'A',
                'branch_id' => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(AccountReceivablePayableService::class)->removeDebtAccountByCashMovementId((int) $cashMovement->id);
    }

    private function registerPurchaseDebt(
        Movement $movement,
        Person $person,
        array $validated,
        float $total,
        int $branchId,
        $user,
        \Carbon\CarbonInterface $dueDate
    ): void {
        $cashRegisterId = (int) ($validated['cash_register_id'] ?? 0);
        if ($cashRegisterId <= 0) {
            throw new \RuntimeException('Debe seleccionar una caja para registrar la deuda de la compra.');
        }

        $cashRegister = CashRegister::query()
            ->where('branch_id', $branchId)
            ->findOrFail($cashRegisterId);

        $paymentConcept = $this->resolvePurchasePaymentConcept();
        $cashMovementTypeId = $this->resolveCashMovementTypeId();
        $cashDocumentTypeId = $this->resolveCashExpenseDocumentTypeId($cashMovementTypeId);
        $shift = Shift::query()->where('branch_id', $branchId)->first() ?? Shift::query()->first();

        if (!$shift) {
            throw new \RuntimeException('No hay turno disponible para registrar la deuda de la compra.');
        }

        $cashOutMovement = Movement::query()->create([
            'number' => $this->generateCashMovementNumber($branchId, $cashRegisterId, (int) $paymentConcept->id),
            'moved_at' => $validated['moved_at'],
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistema',
            'person_id' => $person->id,
            'person_name' => trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')),
            'responsible_id' => $user?->id,
            'responsible_name' => $user?->name ?? 'Sistema',
            'comment' => 'Registro de deuda de compra ' . $movement->number,
            'status' => '1',
            'movement_type_id' => $cashMovementTypeId,
            'document_type_id' => $cashDocumentTypeId,
            'branch_id' => $branchId,
            'parent_movement_id' => $movement->id,
            'shift_id' => $shift->id,
            'shift_snapshot' => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ],
        ]);

        $cashMovement = CashMovements::query()->create([
            'payment_concept_id' => $paymentConcept->id,
            'currency' => $validated['currency'],
            'exchange_rate' => (float) $validated['exchange_rate'],
            'total' => $total,
            'cash_register_id' => $cashRegisterId,
            'cash_register' => $cashRegister->number ?? 'Caja Principal',
            'shift_id' => $shift->id,
            'shift_snapshot' => [
                'name' => $shift->name,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
            ],
            'movement_id' => $cashOutMovement->id,
            'branch_id' => $branchId,
        ]);

        $debtPaymentMethod = $this->resolveDebtPaymentMethod();

        DB::table('cash_movement_details')->insert([
            'cash_movement_id' => $cashMovement->id,
            'type' => 'DEUDA',
            'due_at' => $dueDate,
            'paid_at' => null,
            'payment_method_id' => $debtPaymentMethod->id,
            'payment_method' => $debtPaymentMethod->description ?? 'Deuda',
            'number' => $cashOutMovement->number,
            'card_id' => null,
            'card' => '',
            'bank_id' => null,
            'bank' => '',
            'digital_wallet_id' => null,
            'digital_wallet' => '',
            'payment_gateway_id' => null,
            'payment_gateway' => '',
            'amount' => $total,
            'comment' => $validated['comment'] ?? ('Compra registrada como deuda ' . $movement->number),
            'status' => 'A',
            'branch_id' => $branchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccountReceivablePayableService::class)->syncDebtAccount(
            $cashMovement,
            AccountReceivablePayableService::TYPE_PAYABLE,
            $dueDate
        );
    }

    private function resolvePurchaseDueDate(array $validated, Person $person): \Carbon\CarbonInterface
    {
        if (!empty($validated['due_date'])) {
            return \Carbon\Carbon::parse((string) $validated['due_date']);
        }

        return \Carbon\Carbon::parse((string) $validated['moved_at'])
            ->addDays((int) ($person->credit_days ?? 0));
    }

    private function buildInitialItemsFromWorkshopQuotation(int $quotationId, int $branchId): array
    {
        if ($quotationId <= 0 || $branchId <= 0) {
            return [];
        }

        $wm = WorkshopMovement::query()
            ->with(['details' => function ($q) {
                $q->whereNull('deleted_at');
            }, 'details.product'])
            ->find($quotationId);

        if (
            !$wm
            || (int) $wm->branch_id !== $branchId
            || (string) ($wm->quotation_source ?? '') !== 'external'
            || $wm->vehicle_id
        ) {
            return [];
        }

        $defaultUnitId = (int) (Unit::query()->orderBy('id')->value('id') ?? 0);

        return $wm->details
            ->filter(fn ($d) => strtoupper((string) ($d->line_type ?? '')) === 'PART')
            ->values()
            ->map(function ($d) use ($defaultUnitId) {
                $uid = (int) ($d->product?->base_unit_id ?? 0);
                if ($uid <= 0) {
                    $uid = $defaultUnitId;
                }

                return [
                    'product_id' => $d->product_id ? (int) $d->product_id : null,
                    'unit_id' => $uid,
                    'description' => (string) $d->description,
                    'quantity' => (float) $d->qty,
                    'amount' => (float) $d->total,
                    'comment' => '',
                ];
            })
            ->all();
    }

    private function deletePurchaseFinancialRecords(Movement $purchase): void
    {
        $cashMovements = CashMovements::query()
            ->with(['details', 'movement'])
            ->whereHas('movement', fn ($query) => $query->where('parent_movement_id', $purchase->id))
            ->get();

        foreach ($cashMovements as $cashMovement) {
            app(AccountReceivablePayableService::class)->removeDebtAccountByCashMovementId((int) $cashMovement->id);
            $cashMovement->details()->delete();
            $cashMovementMovement = $cashMovement->movement;
            $cashMovement->delete();
            $cashMovementMovement?->delete();
        }
    }

    private function resolveCashMovementTypeId(): int
    {
        $movementTypeId = MovementType::query()
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%caja%')
                    ->orWhere('description', 'ILIKE', '%cash%');
            })
            ->orderBy('id')
            ->value('id');

        if (!$movementTypeId) {
            $movementTypeId = MovementType::query()->find(4)?->id;
        }

        if (!$movementTypeId) {
            $movementTypeId = MovementType::query()->orderBy('id')->value('id');
        }

        if (!$movementTypeId) {
            throw new \RuntimeException('No se encontro tipo de movimiento para caja.');
        }

        return (int) $movementTypeId;
    }

    private function resolveCashExpenseDocumentTypeId(int $cashMovementTypeId): int
    {
        $documentTypeId = DocumentType::query()
            ->where('movement_type_id', $cashMovementTypeId)
            ->where('name', 'ILIKE', '%egreso%')
            ->orderBy('id')
            ->value('id');

        if (!$documentTypeId) {
            $documentTypeId = DocumentType::query()
                ->where('movement_type_id', $cashMovementTypeId)
                ->orderBy('id')
                ->value('id');
        }

        if (!$documentTypeId) {
            throw new \RuntimeException('No se encontro tipo de documento para salida de caja.');
        }

        return (int) $documentTypeId;
    }

    private function resolvePurchasePaymentConcept(): PaymentConcept
    {
        $paymentConcept = PaymentConcept::query()
            ->where('type', 'E')
            ->where(function ($query) {
                $query->where('description', 'ILIKE', '%compra%')
                    ->orWhere('description', 'ILIKE', '%proveedor%');
            })
            ->orderBy('id')
            ->first();

        if (!$paymentConcept) {
            $paymentConcept = PaymentConcept::query()
                ->where('type', 'E')
                ->orderBy('id')
                ->first();
        }

        if (!$paymentConcept) {
            throw new \RuntimeException('No se encontro concepto de pago de egreso para compras.');
        }

        return $paymentConcept;
    }

    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, ?int $paymentConceptId = null): string
    {
        $lastRecord = Movement::query()
            ->select('movements.number')
            ->join('cash_movements', 'cash_movements.movement_id', '=', 'movements.id')
            ->where('movements.branch_id', $branchId)
            ->where('cash_movements.cash_register_id', $cashRegisterId)
            ->when($paymentConceptId !== null, function ($query) use ($paymentConceptId) {
                $query->where('cash_movements.payment_concept_id', $paymentConceptId);
            })
            ->lockForUpdate()
            ->orderByDesc('movements.number')
            ->first();

        $lastNumber = $lastRecord?->number;
        $nextSequence = $lastNumber ? ((int) $lastNumber + 1) : 1;

        return str_pad((string) $nextSequence, 8, '0', STR_PAD_LEFT);
    }

    private function resolveDebtPaymentMethod(): PaymentMethod
    {
        $paymentMethod = PaymentMethod::query()
            ->where('description', 'ILIKE', 'deuda')
            ->where('status', true)
            ->first();

        if ($paymentMethod) {
            return $paymentMethod;
        }

        return PaymentMethod::query()->create([
            'description' => 'Deuda',
            'order_num' => (int) (PaymentMethod::query()->max('order_num') ?? 0) + 1,
            'status' => true,
        ]);
    }

    private function assertValidPurchaseMovement(Movement $movement): void
    {
        $movement->loadMissing('purchaseMovement');
        if (!$movement->purchaseMovement) {
            abort(404, 'Compra no encontrada.');
        }

        $branchId = (int) session('branch_id');
        if ((int) $movement->branch_id !== $branchId) {
            abort(403, 'No tienes permiso para esta compra.');
        }
    }
}


