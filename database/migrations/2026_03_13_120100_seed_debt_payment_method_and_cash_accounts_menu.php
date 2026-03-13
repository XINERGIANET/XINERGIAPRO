<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $debtPaymentMethod = DB::table('payment_methods')
            ->whereRaw('LOWER(description) = ?', ['deuda'])
            ->whereNull('deleted_at')
            ->first();

        if (!$debtPaymentMethod) {
            DB::table('payment_methods')->insert([
                'description' => 'Deuda',
                'order_num' => (int) (DB::table('payment_methods')->max('order_num') ?? 0) + 1,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $moduleId = DB::table('modules')->where('name', 'Caja')->value('id');
        $viewId = DB::table('views')->orderBy('id')->value('id');

        if (!$moduleId || !$viewId) {
            return;
        }

        $menuOptions = [
            [
                'name' => 'Cuentas por cobrar',
                'icon' => 'ri-money-dollar-circle-line',
                'action' => '/admin/caja/cuentas-por-cobrar',
            ],
            [
                'name' => 'Cuentas por pagar',
                'icon' => 'ri-file-list-3-line',
                'action' => '/admin/caja/cuentas-por-pagar',
            ],
        ];

        $menuOptionIds = [];

        foreach ($menuOptions as $option) {
            $existingId = DB::table('menu_option')
                ->where('module_id', $moduleId)
                ->where('name', $option['name'])
                ->value('id');

            if ($existingId) {
                DB::table('menu_option')
                    ->where('id', $existingId)
                    ->update([
                        'icon' => $option['icon'],
                        'action' => $option['action'],
                        'view_id' => $viewId,
                        'status' => 1,
                        'updated_at' => $now,
                    ]);

                $menuOptionIds[] = (int) $existingId;
                continue;
            }

            $menuOptionIds[] = (int) DB::table('menu_option')->insertGetId([
                'name' => $option['name'],
                'icon' => $option['icon'],
                'action' => $option['action'],
                'module_id' => $moduleId,
                'view_id' => $viewId,
                'status' => 1,
                'quick_access' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionPairs = DB::table('user_permission')
            ->select('profile_id', 'branch_id')
            ->distinct()
            ->get();

        foreach ($permissionPairs as $pair) {
            foreach ($menuOptionIds as $menuOptionId) {
                $exists = DB::table('user_permission')
                    ->where('profile_id', $pair->profile_id)
                    ->where('branch_id', $pair->branch_id)
                    ->where('menu_option_id', $menuOptionId)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('user_permission')->insert([
                    'id' => (string) Str::uuid(),
                    'name' => DB::table('menu_option')->where('id', $menuOptionId)->value('name') ?? 'Menu',
                    'profile_id' => $pair->profile_id,
                    'menu_option_id' => $menuOptionId,
                    'branch_id' => $pair->branch_id,
                    'status' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $menuOptionIds = DB::table('menu_option')
            ->whereIn('action', [
                '/admin/caja/cuentas-por-cobrar',
                '/admin/caja/cuentas-por-pagar',
            ])
            ->pluck('id');

        if ($menuOptionIds->isNotEmpty()) {
            DB::table('user_permission')->whereIn('menu_option_id', $menuOptionIds)->delete();
            DB::table('menu_option')->whereIn('id', $menuOptionIds)->delete();
        }

        DB::table('payment_methods')
            ->whereRaw('LOWER(description) = ?', ['deuda'])
            ->delete();
    }
};
