<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkshopOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $operationsByView = [
            'TAL_CITAS' => [
                ['name' => 'Nueva cita', 'icon' => 'ri-add-line', 'action' => 'open-create-modal', 'color' => '#111827', 'status' => 1, 'type' => 'T'],
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.appointments.index', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'R'],
                ['name' => 'Editar', 'icon' => 'ri-edit-line', 'action' => 'workshop.appointments.update', 'color' => '#0f766e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Eliminar', 'icon' => 'ri-delete-bin-line', 'action' => 'workshop.appointments.destroy', 'color' => '#dc2626', 'status' => 1, 'type' => 'R'],
            ],
            'TAL_VEH' => [
                ['name' => 'Nuevo vehiculo', 'icon' => 'ri-add-line', 'action' => 'open-create-modal', 'color' => '#111827', 'status' => 1, 'type' => 'T'],
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.vehicles.index', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'R'],
                ['name' => 'Editar', 'icon' => 'ri-edit-line', 'action' => 'workshop.vehicles.update', 'color' => '#0f766e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Eliminar', 'icon' => 'ri-delete-bin-line', 'action' => 'workshop.vehicles.destroy', 'color' => '#dc2626', 'status' => 1, 'type' => 'R'],
            ],
            'TAL_OS' => [
                ['name' => 'Crear', 'icon' => 'ri-add-line', 'action' => 'workshop.orders.create', 'color' => '#111827', 'status' => 1, 'type' => 'T'],
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.orders.show', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'R'],
                ['name' => 'Editar', 'icon' => 'ri-edit-line', 'action' => 'workshop.orders.update', 'color' => '#0f766e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Eliminar', 'icon' => 'ri-delete-bin-line', 'action' => 'workshop.orders.destroy', 'color' => '#dc2626', 'status' => 1, 'type' => 'R'],
                ['name' => 'Generar cotizacion', 'icon' => 'ri-file-list-3-line', 'action' => 'workshop.orders.quotation', 'color' => '#0f766e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Aprobar', 'icon' => 'ri-check-double-line', 'action' => 'workshop.orders.approve', 'color' => '#4f46e5', 'status' => 1, 'type' => 'R'],
                ['name' => 'Consumir stock', 'icon' => 'ri-box-3-line', 'action' => 'workshop.orders.consume', 'color' => '#b45309', 'status' => 1, 'type' => 'R'],
                ['name' => 'Asignar tecnicos', 'icon' => 'ri-team-line', 'action' => 'workshop.orders.technicians.assign', 'color' => '#0e7490', 'status' => 1, 'type' => 'R'],
                ['name' => 'Generar venta', 'icon' => 'ri-shopping-bag-3-line', 'action' => 'workshop.orders.sale', 'color' => '#7e22ce', 'status' => 1, 'type' => 'R'],
                ['name' => 'Registrar pago', 'icon' => 'ri-cash-line', 'action' => 'workshop.orders.payment', 'color' => '#166534', 'status' => 1, 'type' => 'R'],
                ['name' => 'Registrar devolucion', 'icon' => 'ri-refund-2-line', 'action' => 'workshop.orders.payment.refund', 'color' => '#be123c', 'status' => 1, 'type' => 'R'],
                ['name' => 'Registrar garantia', 'icon' => 'ri-shield-check-line', 'action' => 'workshop.orders.warranty.store', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'R'],
                ['name' => 'Anular', 'icon' => 'ri-close-circle-line', 'action' => 'workshop.orders.cancel', 'color' => '#b91c1c', 'status' => 1, 'type' => 'R'],
                ['name' => 'Reabrir', 'icon' => 'ri-loop-right-line', 'action' => 'workshop.orders.reopen', 'color' => '#92400e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Imprimir PDF', 'icon' => 'ri-file-pdf-line', 'action' => 'workshop.pdf.order', 'color' => '#334155', 'status' => 1, 'type' => 'R'],
            ],
            'TAL_SERV' => [
                ['name' => 'Crear', 'icon' => 'ri-add-line', 'action' => 'open-create-modal', 'color' => '#111827', 'status' => 1, 'type' => 'T'],
                ['name' => 'Editar', 'icon' => 'ri-edit-line', 'action' => 'workshop.services.update', 'color' => '#0f766e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Eliminar', 'icon' => 'ri-delete-bin-line', 'action' => 'workshop.services.destroy', 'color' => '#dc2626', 'status' => 1, 'type' => 'R'],
            ],
            'TAL_COMP' => [
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.purchases.index', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'T'],
                ['name' => 'Exportar', 'icon' => 'ri-file-excel-2-line', 'action' => 'workshop.reports.export.purchases', 'color' => '#166534', 'status' => 1, 'type' => 'T'],
            ],
            'TAL_VENT' => [
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.sales-register.index', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'T'],
                ['name' => 'Exportar', 'icon' => 'ri-file-excel-2-line', 'action' => 'workshop.reports.export.sales', 'color' => '#166534', 'status' => 1, 'type' => 'T'],
            ],
            'TAL_ARM' => [
                ['name' => 'Crear', 'icon' => 'ri-add-line', 'action' => 'workshop.assemblies.store', 'color' => '#111827', 'status' => 1, 'type' => 'T'],
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.assemblies.index', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'R'],
                ['name' => 'Editar', 'icon' => 'ri-edit-line', 'action' => 'workshop.assemblies.update', 'color' => '#0f766e', 'status' => 1, 'type' => 'R'],
                ['name' => 'Eliminar', 'icon' => 'ri-delete-bin-line', 'action' => 'workshop.assemblies.destroy', 'color' => '#dc2626', 'status' => 1, 'type' => 'R'],
                ['name' => 'Exportar', 'icon' => 'ri-file-excel-2-line', 'action' => 'workshop.assemblies.export', 'color' => '#166534', 'status' => 1, 'type' => 'T'],
            ],
            'TAL_REP' => [
                ['name' => 'Ver', 'icon' => 'ri-eye-line', 'action' => 'workshop.reports.index', 'color' => '#1d4ed8', 'status' => 1, 'type' => 'T'],
                ['name' => 'Exportar', 'icon' => 'ri-file-excel-2-line', 'action' => 'workshop.reports.export.sales', 'color' => '#166534', 'status' => 1, 'type' => 'T'],
                ['name' => 'Imprimir PDF', 'icon' => 'ri-file-pdf-line', 'action' => 'workshop.pdf.order', 'color' => '#334155', 'status' => 1, 'type' => 'R'],
            ],
        ];

        $viewIdMap = DB::table('views')->whereIn('abbreviation', array_keys($operationsByView))->pluck('id', 'abbreviation');

        $operationIdsByAbbreviation = [];

        foreach ($operationsByView as $abbreviation => $operations) {
            $viewId = $viewIdMap[$abbreviation] ?? null;
            if (!$viewId) {
                continue;
            }

            foreach ($operations as $operationData) {
                $existing = DB::table('operations')
                    ->where('view_id', $viewId)
                    ->where('name', $operationData['name'])
                    ->first();

                if ($existing) {
                    DB::table('operations')->where('id', $existing->id)->update([
                        'icon' => $operationData['icon'],
                        'action' => $operationData['action'],
                        'color' => $operationData['color'],
                        'status' => $operationData['status'],
                        'type' => $operationData['type'],
                        'updated_at' => $now,
                    ]);
                    $operationIdsByAbbreviation[$abbreviation][] = $existing->id;
                    continue;
                }

                $operationId = DB::table('operations')->insertGetId([
                    'name' => $operationData['name'],
                    'icon' => $operationData['icon'],
                    'action' => $operationData['action'],
                    'view_id' => $viewId,
                    'view_id_action' => $viewId,
                    'color' => $operationData['color'],
                    'status' => $operationData['status'],
                    'type' => $operationData['type'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $operationIdsByAbbreviation[$abbreviation][] = $operationId;
            }
        }

        $branchIds = DB::table('branches')->pluck('id');
        $targetProfiles = $this->resolveTargetProfiles();

        foreach ($branchIds as $branchId) {
            foreach ($operationIdsByAbbreviation as $operationIds) {
                foreach ($operationIds as $operationId) {
                    $branchOperation = DB::table('branch_operation')
                        ->where('branch_id', $branchId)
                        ->where('operation_id', $operationId)
                        ->first();

                    if ($branchOperation) {
                        DB::table('branch_operation')
                            ->where('id', $branchOperation->id)
                            ->update(['status' => 1, 'deleted_at' => null, 'updated_at' => $now]);
                    } else {
                        DB::table('branch_operation')->insert([
                            'operation_id' => $operationId,
                            'branch_id' => $branchId,
                            'status' => 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }

                    foreach ($targetProfiles as $profileName => $profileId) {
                        $adminOnly = in_array($operationId, $this->adminOnlyOperationIds($operationIdsByAbbreviation), true);
                    $allowed = match ($profileName) {
                        'ADMIN' => true,
                        'RECEPCION' => !$adminOnly,
                        'TECNICO' => !in_array($operationId, $this->restrictedOperationIds($operationIdsByAbbreviation), true),
                        'CAJERO' => in_array($operationId, $this->cashOperationIds($operationIdsByAbbreviation), true) && !$adminOnly,
                        'ALMACENERO' => in_array($operationId, array_values(array_unique(array_merge(
                            $this->stockOperationIds($operationIdsByAbbreviation),
                            $this->assemblyOperationIds($operationIdsByAbbreviation)
                        ))), true),
                        default => false,
                    };

                        $existing = DB::table('operation_profile_branch')
                            ->where('operation_id', $operationId)
                            ->where('profile_id', $profileId)
                            ->where('branch_id', $branchId)
                            ->first();

                        if ($existing) {
                            DB::table('operation_profile_branch')
                                ->where('operation_id', $operationId)
                                ->where('profile_id', $profileId)
                                ->where('branch_id', $branchId)
                                ->update([
                                    'status' => $allowed ? 1 : 0,
                                    'deleted_at' => null,
                                    'updated_at' => $now,
                                ]);
                        } else {
                            DB::table('operation_profile_branch')->insert([
                                'operation_id' => $operationId,
                                'profile_id' => $profileId,
                                'branch_id' => $branchId,
                                'status' => $allowed ? 1 : 0,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }
            }
        }

        $this->assignMenuPermissions($targetProfiles, $branchIds->all(), $now);
    }

    private function resolveTargetProfiles(): array
    {
        $profileMap = [];

        $definitions = [
            'ADMIN' => 'Administrador de sistema',
            'RECEPCION' => 'Recepcion',
            'TECNICO' => 'Tecnico',
            'CAJERO' => 'Cajero',
            'ALMACENERO' => 'Almacenero',
        ];

        foreach ($definitions as $key => $name) {
            $profileId = DB::table('profiles')
                ->whereRaw('UPPER(name) = ?', [Str::upper($name)])
                ->value('id');

            if (!$profileId) {
                $profileId = DB::table('profiles')->insertGetId([
                    'name' => $name,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $profileMap[$key] = (int) $profileId;

            foreach (DB::table('branches')->pluck('id') as $branchId) {
                $exists = DB::table('profile_branch')
                    ->where('profile_id', $profileId)
                    ->where('branch_id', $branchId)
                    ->first();

                if ($exists) {
                    DB::table('profile_branch')->where('profile_id', $profileId)->where('branch_id', $branchId)
                        ->update(['deleted_at' => null, 'updated_at' => now()]);
                } else {
                    DB::table('profile_branch')->insert([
                        'profile_id' => $profileId,
                        'branch_id' => $branchId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return $profileMap;
    }

    private function restrictedOperationIds(array $operationIdsByAbbreviation): array
    {
        return array_values(array_unique(array_merge(
            $this->cashOperationIds($operationIdsByAbbreviation),
            $this->stockOperationIds($operationIdsByAbbreviation),
            $this->adminOnlyOperationIds($operationIdsByAbbreviation)
        )));
    }

    private function cashOperationIds(array $operationIdsByAbbreviation): array
    {
        $ids = [];
        $targetNames = ['Registrar pago', 'Registrar devolucion'];
        foreach ($operationIdsByAbbreviation as $abbr => $operationIds) {
            $records = DB::table('operations')->whereIn('id', $operationIds)->whereIn('name', $targetNames)->pluck('id')->all();
            $ids = array_merge($ids, $records);
        }

        return array_values(array_unique($ids));
    }

    private function stockOperationIds(array $operationIdsByAbbreviation): array
    {
        $ids = [];
        $targetNames = ['Consumir stock'];
        foreach ($operationIdsByAbbreviation as $abbr => $operationIds) {
            $records = DB::table('operations')->whereIn('id', $operationIds)->whereIn('name', $targetNames)->pluck('id')->all();
            $ids = array_merge($ids, $records);
        }

        return array_values(array_unique($ids));
    }

    private function adminOnlyOperationIds(array $operationIdsByAbbreviation): array
    {
        $ids = [];
        $targetNames = ['Anular', 'Reabrir'];
        foreach ($operationIdsByAbbreviation as $operationIds) {
            $records = DB::table('operations')->whereIn('id', $operationIds)->whereIn('name', $targetNames)->pluck('id')->all();
            $ids = array_merge($ids, $records);
        }

        return array_values(array_unique($ids));
    }

    private function assemblyOperationIds(array $operationIdsByAbbreviation): array
    {
        $ids = [];
        $targetNames = ['Crear', 'Ver', 'Editar', 'Eliminar', 'Exportar'];
        $viewId = DB::table('views')->where('abbreviation', 'TAL_ARM')->value('id');
        if (!$viewId) {
            return [];
        }

        foreach ($operationIdsByAbbreviation as $operationIds) {
            $records = DB::table('operations')
                ->whereIn('id', $operationIds)
                ->where('view_id', $viewId)
                ->whereIn('name', $targetNames)
                ->pluck('id')
                ->all();
            $ids = array_merge($ids, $records);
        }

        return array_values(array_unique($ids));
    }

    private function assignMenuPermissions(array $profiles, array $branchIds, $now): void
    {
        $moduleId = DB::table('modules')->where('name', 'Taller')->value('id');
        if (!$moduleId) {
            return;
        }

        $menuOptions = DB::table('menu_option')->where('module_id', $moduleId)->pluck('id', 'name');

        foreach ($profiles as $profileName => $profileId) {
            foreach ($branchIds as $branchId) {
                foreach ($menuOptions as $menuName => $menuId) {
                    $allowed = match ($profileName) {
                        'ADMIN' => true,
                        'RECEPCION' => true,
                        'TECNICO' => !in_array($menuName, ['Reportes Taller'], true),
                        'CAJERO' => in_array($menuName, ['Ordenes de Servicio', 'Reportes Taller', 'Ventas Taller'], true),
                        'ALMACENERO' => in_array($menuName, ['Ordenes de Servicio', 'Armados Taller', 'Compras Taller'], true),
                        default => false,
                    };

                    $existing = DB::table('user_permission')
                        ->where('profile_id', $profileId)
                        ->where('branch_id', $branchId)
                        ->where('menu_option_id', $menuId)
                        ->first();

                    if ($existing) {
                        DB::table('user_permission')
                            ->where('id', $existing->id)
                            ->update([
                                'name' => $menuName,
                                'status' => $allowed ? 1 : 0,
                                'deleted_at' => null,
                                'updated_at' => $now,
                            ]);
                    } else {
                        DB::table('user_permission')->insert([
                            'id' => (string) Str::uuid(),
                            'name' => $menuName,
                            'profile_id' => $profileId,
                            'menu_option_id' => $menuId,
                            'branch_id' => $branchId,
                            'status' => $allowed ? 1 : 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }
}

