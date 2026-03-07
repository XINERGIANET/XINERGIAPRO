<?php

namespace App\Console\Commands;

use App\Models\WorkshopMaintenanceReminder;
use App\Models\WorkshopMovement;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncWorkshopMaintenanceReminders extends Command
{
    protected $signature = 'workshop:sync-maintenance-reminders {--branch_id=}';

    protected $description = 'Recalcula recordatorios de mantenimiento por vehiculo y cliente.';

    public function handle(): int
    {
        $branchId = (int) ($this->option('branch_id') ?? 0);

        $groups = WorkshopMovement::query()
            ->selectRaw('branch_id, company_id, vehicle_id, client_person_id, MAX(id) as last_workshop_movement_id')
            ->whereNotNull('vehicle_id')
            ->whereNotNull('client_person_id')
            ->whereIn('status', ['finished', 'delivered'])
            ->when($branchId > 0, fn ($query) => $query->where('branch_id', $branchId))
            ->groupBy('branch_id', 'company_id', 'vehicle_id', 'client_person_id')
            ->get();

        foreach ($groups as $group) {
            $history = WorkshopMovement::query()
                ->where('vehicle_id', $group->vehicle_id)
                ->where('branch_id', $group->branch_id)
                ->whereIn('status', ['finished', 'delivered'])
                ->orderBy('intake_date')
                ->get(['id', 'delivery_date', 'intake_date']);

            if ($history->isEmpty()) {
                continue;
            }

            $dates = $history
                ->map(fn ($row) => $row->delivery_date?->copy()?->startOfDay() ?? $row->intake_date?->copy()?->startOfDay())
                ->filter()
                ->values();

            $averageFrequency = 0;
            if ($dates->count() > 1) {
                $diffs = [];
                for ($i = 1; $i < $dates->count(); $i++) {
                    $diffs[] = max(1, $dates[$i - 1]->diffInDays($dates[$i]));
                }

                if (!empty($diffs)) {
                    $averageFrequency = (int) round(array_sum($diffs) / count($diffs));
                }
            }

            $configuredPeriod = $this->parameterNumber([
                'Periodo de mantenimiento',
            ], (int) $group->branch_id, 30);

            $notifyBefore = $this->parameterNumber([
                'recordatorio mantenimiento',
                'dias previos',
            ], (int) $group->branch_id, 3);

            $periodDays = max(1, $averageFrequency > 0 ? $averageFrequency : $configuredPeriod);
            $lastDate = $dates->last();
            $nextServiceDate = $lastDate?->copy()->addDays($periodDays);
            $notifyAt = $nextServiceDate?->copy()->subDays(max(0, $notifyBefore));

            WorkshopMaintenanceReminder::query()->updateOrCreate(
                [
                    'branch_id' => $group->branch_id,
                    'vehicle_id' => $group->vehicle_id,
                ],
                [
                    'company_id' => $group->company_id,
                    'client_person_id' => $group->client_person_id,
                    'last_workshop_movement_id' => $group->last_workshop_movement_id,
                    'average_frequency_days' => $averageFrequency,
                    'configured_period_days' => $configuredPeriod,
                    'last_service_date' => $lastDate?->toDateString(),
                    'next_service_date' => $nextServiceDate?->toDateString(),
                    'notify_at' => $notifyAt?->toDateString(),
                    'status' => $notifyAt && $notifyAt->lessThanOrEqualTo(Carbon::today()) ? 'due' : 'pending',
                    'notified_at' => null,
                ]
            );
        }

        $this->info('Recordatorios de mantenimiento sincronizados.');

        return self::SUCCESS;
    }

    private function parameterNumber(array $needles, int $branchId, int $default): int
    {
        $parameter = DB::table('parameters')
            ->where(function ($query) use ($needles) {
                foreach ($needles as $needle) {
                    $query->orWhere('description', 'ILIKE', '%' . $needle . '%');
                }
            })
            ->orderBy('id')
            ->first();

        if (!$parameter) {
            return $default;
        }

        $branchValue = DB::table('branch_parameters')
            ->where('parameter_id', $parameter->id)
            ->where('branch_id', $branchId)
            ->whereNull('deleted_at')
            ->value('value');

        $raw = $branchValue ?? $parameter->value;

        return is_numeric($raw) ? (int) $raw : $default;
    }
}
