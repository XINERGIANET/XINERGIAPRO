<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class WorkshopAuthorization
{
    public static function ensureAllowed(string $action): void
    {
        $branchId = (int) session('branch_id');
        $profileId = (int) (session('profile_id') ?? auth()->user()?->profile_id ?? 0);

        if ($branchId <= 0 || $profileId <= 0) {
            abort(403, 'Sin contexto de sucursal/perfil.');
        }

        $operation = DB::table('operations')
            ->where('action', $action)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->first();

        // Si la ruta no está modelada como operación, no bloqueamos aquí.
        if (!$operation) {
            return;
        }

        $branchEnabled = DB::table('branch_operation')
            ->where('operation_id', $operation->id)
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->exists();

        if (!$branchEnabled) {
            abort(403, 'Operación no habilitada para la sucursal.');
        }

        $profileEnabled = DB::table('operation_profile_branch')
            ->where('operation_id', $operation->id)
            ->where('profile_id', $profileId)
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->exists();

        // if (!$profileEnabled) {
        //     abort(403, 'Perfil sin permiso para esta operación.');
        // }
    }
}

