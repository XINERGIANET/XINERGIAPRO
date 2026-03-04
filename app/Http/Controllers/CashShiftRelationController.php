<?php

namespace App\Http\Controllers;

use App\Models\CashMovements;
use App\Models\CashShiftRelation;
use App\Models\Operation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CashShiftRelationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $viewId = $request->input('view_id');
        $branchId = (int) $request->session()->get('branch_id');
        $profileId = $request->session()->get('profile_id') ?? $request->user()?->profile_id;
        $operaciones = collect();

        if ($viewId && $branchId && $profileId) {
            $operaciones = Operation::query()
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

        $relations = CashShiftRelation::query()
            ->with([
                'cashMovementStart:id,total,cash_register_id,shift_id,movement_id',
                'cashMovementStart.cashRegister:id,number,series',
                'cashMovementStart.shift:id,name',
                'cashMovementStart.movement:id,number,moved_at',
                'cashMovementStart.details:id,cash_movement_id,payment_method,amount,card,payment_gateway,digital_wallet,bank',
                'cashMovementEnd:id,total,movement_id',
                'cashMovementEnd.movement:id,number,moved_at',
                'cashMovementEnd.details:id,cash_movement_id,payment_method,amount,card,payment_gateway,digital_wallet,bank',
            ])
            ->where('branch_id', $branchId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner
                        ->whereHas('cashMovementStart.cashRegister', function ($q) use ($search) {
                            $q->where('number', 'ILIKE', "%{$search}%")
                                ->orWhere('series', 'ILIKE', "%{$search}%");
                        })
                        ->orWhereHas('cashMovementStart.shift', function ($q) use ($search) {
                            $q->where('name', 'ILIKE', "%{$search}%");
                        });

                    $inner->orWhereRaw('CAST(cash_shift_relations.id AS TEXT) ILIKE ?', ["%{$search}%"]);
                });
            })
            ->latest('started_at')
            ->paginate($perPage)
            ->withQueryString();

        $relations->getCollection()->transform(function (CashShiftRelation $relation) use ($branchId) {
            $relation->turn_summary = $this->buildTurnSummary($relation, $branchId);
            return $relation;
        });

        return view('cash_shift_relations.index', [
            'title' => 'Relacion Caja - Turno',
            'relations' => $relations,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
        ]);
    }

    private function buildTurnSummary(CashShiftRelation $relation, int $branchId): Collection
    {
        $summary = collect();
        $start = $relation->cashMovementStart;
        $end = $relation->cashMovementEnd;

        if ($start) {
            $summary->push([
                'label' => 'Apertura de caja',
                'icon' => 'ri-safe-2-line',
                'total' => (float) $start->total,
                'details' => $this->groupDetailsByMethod($start->details ?? collect()),
            ]);
        }

        if ($start?->cash_register_id) {
            $from = Carbon::parse($relation->started_at);
            $to = $relation->ended_at ? Carbon::parse($relation->ended_at) : now();

            $periodMovements = CashMovements::query()
                ->with([
                    'paymentConcept:id,description,type',
                    'details:id,cash_movement_id,payment_method,amount,card,payment_gateway,digital_wallet,bank',
                    'movement:id,moved_at',
                ])
                ->where('branch_id', $branchId)
                ->where('cash_register_id', $start->cash_register_id)
                ->whereHas('movement', function ($q) use ($from, $to) {
                    $q->whereBetween('moved_at', [$from, $to]);
                })
                ->when($start->id, fn ($q) => $q->where('id', '!=', $start->id))
                ->when($end?->id, fn ($q) => $q->where('id', '!=', $end->id))
                ->get();

            $grouped = $periodMovements
                ->groupBy(fn ($cm) => $cm->paymentConcept?->description ?: 'Sin concepto');

            foreach ($grouped as $conceptName => $movements) {
                $allDetails = $movements->pluck('details')->flatten(1);
                $summary->push([
                    'label' => $conceptName,
                    'icon' => $this->resolveConceptIcon((string) $conceptName),
                    'total' => (float) $movements->sum('total'),
                    'details' => $this->groupDetailsByMethod($allDetails),
                ]);
            }
        }

        if ($end) {
            $summary->push([
                'label' => 'Cierre de caja',
                'icon' => 'ri-lock-line',
                'total' => (float) $end->total,
                'details' => $this->groupDetailsByMethod($end->details ?? collect()),
            ]);
        }

        return $summary;
    }

    private function resolveConceptIcon(string $conceptName): string
    {
        $name = mb_strtolower($conceptName, 'UTF-8');

        if (str_contains($name, 'pago')) {
            return 'ri-shopping-cart-2-line';
        }
        if (str_contains($name, 'egreso')) {
            return 'ri-arrow-down-circle-line';
        }
        if (str_contains($name, 'ingreso')) {
            return 'ri-arrow-up-circle-line';
        }

        return 'ri-file-list-3-line';
    }

    private function groupDetailsByMethod(Collection $details): Collection
    {
        return $details
            ->groupBy(function ($detail) {
                $method = trim((string) ($detail->payment_method ?: 'Sin metodo'));
                $suffix = $this->resolveDetailSuffix($detail);
                return $suffix !== '' ? "{$method}|{$suffix}" : $method;
            })
            ->map(function ($group, $key) {
                [$method, $suffix] = array_pad(explode('|', (string) $key, 2), 2, '');
                return [
                    'method' => $method,
                    'suffix' => $suffix,
                    'amount' => (float) collect($group)->sum('amount'),
                ];
            })
            ->values();
    }

    private function resolveDetailSuffix($detail): string
    {
        $parts = array_filter([
            trim((string) ($detail->payment_gateway ?? '')),
            trim((string) ($detail->card ?? '')),
            trim((string) ($detail->digital_wallet ?? '')),
            trim((string) ($detail->bank ?? '')),
        ]);

        return implode(' | ', $parts);
    }
}

