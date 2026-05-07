<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class ParameterHelper
{
    /**
     * Obtiene el valor de un parámetro para la sucursal actual.
     * 
     * @param string $description Descripción exacta del parámetro en la tabla 'parameters'.
     * @param mixed $default Valor por defecto si no se encuentra.
     * @return string
     */
    public static function getBranchValue(string $description, $default = '')
    {
        try {
            $branchId = session('branch_id');
            if (!$branchId) {
                $user = auth()->user();
                $branchId = $user?->person?->branch_id;
            }
            if (!$branchId) {
                return (string) $default;
            }

            $baseParam = DB::table('parameters')
                ->where('description', $description)
                ->where('status', 1)
                ->first();

            if (!$baseParam) {
                return (string) $default;
            }

            $branchVal = DB::table('branch_parameters')
                ->where('parameter_id', $baseParam->id)
                ->where('branch_id', $branchId)
                ->whereNull('deleted_at')
                ->value('value');

            $finalValue = $branchVal ?? $baseParam->value;
            
            return (string) ($finalValue ?? $default);
        } catch (\Throwable $e) {
            return (string) $default;
        }
    }
}
