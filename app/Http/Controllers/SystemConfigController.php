<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use App\Models\ParameterCategories;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemConfigController extends Controller
{
    public function index(Request $request)
    {
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

        $categories = ParameterCategories::query()
            ->whereHas('parameters', fn ($q) => $q->where('status', 1))
            ->with(['parameters' => function ($query) use ($branchId) {
                $query->where('status', 1)
                    ->orderBy('id')
                    ->addSelect('*')
                    ->addSelect([
                        'branch_value' => DB::table('branch_parameters')
                            ->select('value')
                            ->whereColumn('parameter_id', 'parameters.id')
                            ->where('branch_id', $branchId)
                            ->whereNull('deleted_at')
                            ->limit(1),
                    ]);
            }])
            ->orderBy('id')
            ->get();

        $saleDocumentTypes = DB::table('document_types')
            ->where('movement_type_id', 2)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get(['id', 'name']);

        $cashRegisters = DB::table('cash_registers')
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->orderBy('number')
            ->get(['id', 'number']);

        $taxRates = TaxRate::query()
            ->where('status', true)
            ->orderBy('order_num')
            ->orderBy('description')
            ->get(['id', 'description', 'tax_rate']);

        return view('system_config.index', [
            'title' => 'Configuracion de sistema',
            'categories' => $categories,
            'operaciones' => $operaciones,
            'viewId' => $viewId,
            'saleDocumentTypes' => $saleDocumentTypes,
            'cashRegisters' => $cashRegisters,
            'taxRates' => $taxRates,
        ]);
    }

    public function update(Request $request)
    {
        $branchId = (int) $request->session()->get('branch_id');
        $viewId = $request->input('view_id');
        $values = $request->input('values', []);

        if (!is_array($values) || empty($values)) {
            return redirect()
                ->route('admin.system-config.index', $viewId ? ['view_id' => $viewId] : [])
                ->with('error', 'No se recibieron parametros para actualizar.');
        }

        $parameterIds = array_map('intval', array_keys($values));
        $validIds = DB::table('parameters')
            ->whereIn('id', $parameterIds)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($validIds, $values, $branchId) {
            foreach ($validIds as $parameterId) {
                $value = is_scalar($values[$parameterId] ?? null) ? (string) $values[$parameterId] : '';

                $existing = DB::table('branch_parameters')
                    ->where('parameter_id', $parameterId)
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    DB::table('branch_parameters')
                        ->where('id', $existing->id)
                        ->update([
                            'value' => $value,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('branch_parameters')->insert([
                        'value' => $value,
                        'parameter_id' => $parameterId,
                        'branch_id' => $branchId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('admin.system-config.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Configuracion actualizada correctamente.');
    }
}
