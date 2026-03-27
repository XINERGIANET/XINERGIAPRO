<?php

namespace App\Http\Controllers;

use App\Models\WorkshopService;
use App\Models\WorkshopServiceDetail;
use App\Models\WorkshopServiceFrequency;
use App\Support\WorkshopAuthorization;
use App\Support\WorkshopServiceSpreadsheetImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

class WorkshopServiceCatalogController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $routeName = (string) optional($request->route())->getName();
            if (str_starts_with($routeName, 'workshop.')) {
                WorkshopAuthorization::ensureAllowed($routeName);
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 10);

        $services = WorkshopService::query()
            ->with(['priceTiers'])
            ->when($companyId > 0, fn ($query) => $query->where(function ($inner) use ($companyId) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            }))
            ->when($branchId > 0, fn ($query) => $query->where(function ($inner) use ($branchId) {
                $inner->where('branch_id', $branchId)->orWhereNull('branch_id');
            }))
            ->when($search !== '', fn ($query) => $query->where('name', 'ILIKE', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('workshop.services.index', compact('services', 'search', 'perPage'));
    }

    public function importFromSpreadsheet(Request $request): RedirectResponse
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return back()->with('error', 'Ejecuta en el proyecto: composer update (se requiere phpoffice/phpspreadsheet para leer Excel).');
        }

        $validated = $request->validate([
            'import_file' => ['required', File::types(['xlsx', 'xls', 'csv'])->max(12288)],
            'import_type' => ['required', 'in:preventivo,correctivo'],
            'import_estimated_minutes' => ['required', 'integer', 'min:0', 'max:14400'],
        ]);

        $branchId = (int) session('branch_id');
        $branch = \App\Models\Branch::query()->findOrFail($branchId);

        $path = $request->file('import_file')?->getRealPath();
        if (!$path || !is_readable($path)) {
            return back()->with('error', 'No se pudo leer el archivo subido.');
        }

        try {
            $rows = WorkshopServiceSpreadsheetImport::extractRows($path);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $imported = 0;
        $skippedDup = 0;

        DB::transaction(function () use ($rows, $branch, $branchId, $validated, &$imported, &$skippedDup) {
            foreach ($rows as $row) {
                $name = $row['name'];
                $exists = WorkshopService::query()
                    ->where('branch_id', $branchId)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])
                    ->exists();
                if ($exists) {
                    $skippedDup++;

                    continue;
                }

                WorkshopService::query()->create([
                    'company_id' => $branch->company_id,
                    'branch_id' => $branchId,
                    'name' => $name,
                    'type' => $validated['import_type'],
                    'base_price' => $row['price'],
                    'estimated_minutes' => (int) $validated['import_estimated_minutes'],
                    'frequency_each_km' => null,
                    'frequency_enabled' => false,
                    'active' => true,
                ]);
                $imported++;
            }
        });

        $parts = ["Importación lista: {$imported} servicio(s) creado(s)."];
        if ($skippedDup > 0) {
            $parts[] = "{$skippedDup} fila(s) omitidas (el nombre ya existía en esta sucursal).";
        }

        return back()->with('status', implode(' ', $parts));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateServicePayload($request);

        $branchId = (int) session('branch_id');
        $branch = \App\Models\Branch::query()->findOrFail($branchId);

        DB::transaction(function () use ($validated, $branch, $branchId) {
            $service = WorkshopService::query()->create([
                'company_id' => $branch->company_id,
                'branch_id' => $branchId,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'base_price' => $validated['base_price'],
                'estimated_minutes' => $validated['estimated_minutes'],
                'frequency_each_km' => $validated['frequency_each_km'] ?? null,
                'frequency_enabled' => (bool) ($validated['frequency_enabled'] ?? false),
                'has_validity' => (bool) ($validated['has_validity'] ?? false),
                'validity_type' => !empty($validated['has_validity']) ? ($validated['validity_type'] ?? null) : null,
                'active' => (bool) ($validated['active'] ?? true),
            ]);

            $this->syncPriceTiers($service, $validated['normalized_price_tiers']);

            if (!$service->frequency_enabled) {
                WorkshopServiceFrequency::query()->where('workshop_service_id', $service->id)->delete();
            }
        });

        return back()->with('status', 'Servicio registrado correctamente.');
    }

    public function update(Request $request, WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);
        $validated = $this->validateServicePayload($request);

        DB::transaction(function () use ($service, $validated) {
            $service->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'base_price' => $validated['base_price'],
                'estimated_minutes' => $validated['estimated_minutes'],
                'frequency_each_km' => $validated['frequency_each_km'] ?? null,
                'frequency_enabled' => (bool) ($validated['frequency_enabled'] ?? false),
                'has_validity' => (bool) ($validated['has_validity'] ?? false),
                'validity_type' => !empty($validated['has_validity']) ? ($validated['validity_type'] ?? null) : null,
                'active' => (bool) ($validated['active'] ?? false),
            ]);

            $this->syncPriceTiers($service, $validated['normalized_price_tiers']);

            if (!$service->frequency_enabled) {
                WorkshopServiceFrequency::query()->where('workshop_service_id', $service->id)->delete();
            }
        });

        return back()->with('status', 'Servicio actualizado correctamente.');
    }

    public function destroy(WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);
        $service->delete();

        return back()->with('status', 'Servicio eliminado correctamente.');
    }

    public function frequencyEdit(WorkshopService $service): \Illuminate\View\View
    {
        $this->assertServiceScope($service);

        $frequencies = WorkshopServiceFrequency::query()
            ->where('workshop_service_id', $service->id)
            ->orderBy('order_num')
            ->get(['id', 'km', 'multiplier', 'order_num']);

        return view('workshop.services.frequencies', [
            'service' => $service,
            'frequencies' => $frequencies,
        ]);
    }

    public function frequencyUpdate(Request $request, WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);

        $validated = $request->validate([
            'frequency_enabled' => ['nullable', 'boolean'],
            'frequency_each_km' => ['nullable', 'integer', 'min:1'],
            'kms' => ['nullable', 'array'],
            'kms.*' => ['nullable', 'integer', 'min:1'],
            'multipliers' => ['nullable', 'array'],
            'multipliers.*' => ['nullable', 'numeric', 'min:0'],
            'delete_ids' => ['nullable', 'array'],
            'delete_ids.*' => ['nullable', 'integer', 'min:1'],
            'new_km' => ['nullable', 'integer', 'min:1'],
            'new_multiplier' => ['nullable', 'numeric', 'min:0'],
        ]);

        $frequencyEnabled = (bool) ($validated['frequency_enabled'] ?? false);
        $frequencyEachKm = $validated['frequency_each_km'] ?? null;

        DB::transaction(function () use ($service, $frequencyEnabled, $frequencyEachKm, $validated) {
            $service->frequency_enabled = $frequencyEnabled;
            $service->frequency_each_km = $frequencyEachKm;
            $service->save();

            if (!$frequencyEnabled) {
                WorkshopServiceFrequency::query()
                    ->where('workshop_service_id', $service->id)
                    ->delete();
                return;
            }

            // Hard delete de filas marcadas
            $deleteIds = array_map('intval', (array) ($validated['delete_ids'] ?? []));
            if (!empty($deleteIds)) {
                WorkshopServiceFrequency::query()
                    ->where('workshop_service_id', $service->id)
                    ->whereIn('id', $deleteIds)
                    ->forceDelete();
            }

            $kms = (array) ($validated['kms'] ?? []);
            $multipliers = (array) ($validated['multipliers'] ?? []);

            foreach ($kms as $id => $kmValue) {
                $id = (int) $id;
                $km = (int) $kmValue;
                $multiplier = isset($multipliers[$id]) ? (float) $multipliers[$id] : 1.0;

                if ($id <= 0 || $km <= 0) {
                    continue;
                }

                $freq = WorkshopServiceFrequency::query()->where('workshop_service_id', $service->id)->where('id', $id)->first();
                if (!$freq) {
                    continue;
                }

                // Evita colisiones de km por la constraint unique (service_id, km).
                $duplicateKm = WorkshopServiceFrequency::query()
                    ->where('workshop_service_id', $service->id)
                    ->where('km', $km)
                    ->where('id', '!=', $id)
                    ->exists();
                if ($duplicateKm) {
                    continue;
                }

                $freq->km = $km;
                $freq->multiplier = $multiplier;
                $freq->save();
            }

            $newKm = $validated['new_km'] ?? null;
            $newMultiplier = $validated['new_multiplier'] ?? null;

            if ($newKm !== null && $newKm !== '' && $newMultiplier !== null && $newMultiplier !== '') {
                $newKm = (int) $newKm;
                $newMultiplier = (float) $newMultiplier;

                $existing = WorkshopServiceFrequency::query()
                    ->withTrashed()
                    ->where('workshop_service_id', $service->id)
                    ->where('km', $newKm)
                    ->first();

                if ($existing) {
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $existing->order_num = (int) (WorkshopServiceFrequency::query()
                        ->where('workshop_service_id', $service->id)
                        ->max('order_num') ?? 0) + 1;
                    $existing->multiplier = $newMultiplier;
                    $existing->save();
                } else {
                    $nextOrderNum = (int) (WorkshopServiceFrequency::query()
                        ->where('workshop_service_id', $service->id)
                        ->max('order_num') ?? 0) + 1;

                    WorkshopServiceFrequency::query()->create([
                        'workshop_service_id' => $service->id,
                        'km' => $newKm,
                        'multiplier' => $newMultiplier,
                        'order_num' => $nextOrderNum,
                    ]);
                }
            }
        });

        return back()->with('status', 'Frecuencia del servicio actualizada correctamente.');
    }

    public function detailsEdit(WorkshopService $service): \Illuminate\View\View
    {
        $this->assertServiceScope($service);

        $details = WorkshopServiceDetail::query()
            ->where('workshop_service_id', $service->id)
            ->orderBy('order_num')
            ->orderBy('id')
            ->get(['id', 'description', 'order_num']);

        return view('workshop.services.details', [
            'service' => $service,
            'details' => $details,
        ]);
    }

    public function detailsUpdate(Request $request, WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);

        $validated = $request->validate([
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string', 'max:255'],
            'delete_ids' => ['nullable', 'array'],
            'delete_ids.*' => ['nullable', 'integer', 'min:1'],
            'new_description' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($service, $validated) {
            $deleteIds = collect($validated['delete_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            if ($deleteIds !== []) {
                WorkshopServiceDetail::query()
                    ->where('workshop_service_id', $service->id)
                    ->whereIn('id', $deleteIds)
                    ->delete();
            }

            foreach ((array) ($validated['descriptions'] ?? []) as $id => $description) {
                $id = (int) $id;
                $description = trim((string) $description);

                if ($id <= 0 || $description === '') {
                    continue;
                }

                $detail = WorkshopServiceDetail::query()
                    ->where('workshop_service_id', $service->id)
                    ->where('id', $id)
                    ->first();

                if (!$detail) {
                    continue;
                }

                $detail->description = $description;
                $detail->save();
            }

            $newDescription = trim((string) ($validated['new_description'] ?? ''));
            if ($newDescription !== '') {
                $nextOrderNum = (int) (WorkshopServiceDetail::query()
                    ->where('workshop_service_id', $service->id)
                    ->max('order_num') ?? 0) + 1;

                WorkshopServiceDetail::query()->create([
                    'workshop_service_id' => $service->id,
                    'description' => $newDescription,
                    'order_num' => $nextOrderNum,
                ]);
            }
        });

        return back()->with('status', 'Detalle del servicio actualizado correctamente.');
    }

    private function assertServiceScope(WorkshopService $service): void
    {
        $branchId = (int) session('branch_id');
        $branch = $branchId > 0 ? \App\Models\Branch::query()->find($branchId) : null;
        $companyId = (int) ($branch?->company_id ?? 0);

        if ($service->company_id && $companyId > 0 && (int) $service->company_id !== $companyId) {
            abort(404);
        }

        if ($service->branch_id && $branchId > 0 && (int) $service->branch_id !== $branchId) {
            abort(404);
        }
    }

    private function validateServicePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:preventivo,correctivo'],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'has_validity' => ['nullable', 'boolean'],
            'validity_type' => ['nullable', 'string', 'in:soat_vencimiento,revision_tecnica_vencimiento'],
            'frequency_each_km' => ['nullable', 'integer', 'min:1'],
            'frequency_enabled' => ['nullable', 'boolean'],
            'price_tiers' => ['nullable', 'array'],
            'price_tiers.*.max_cc' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'price_tiers.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $normalizedTiers = collect($validated['price_tiers'] ?? [])
            ->map(function ($tier, $index) {
                $maxCc = $tier['max_cc'] ?? null;
                $price = $tier['price'] ?? null;
                $hasMaxCc = $maxCc !== null && $maxCc !== '';
                $hasPrice = $price !== null && $price !== '';

                if ($hasMaxCc xor $hasPrice) {
                    throw ValidationException::withMessages([
                        "price_tiers.{$index}.max_cc" => 'Completa la cilindrada y el precio del tramo.',
                    ]);
                }

                if (!$hasMaxCc && !$hasPrice) {
                    return null;
                }

                return [
                    'max_cc' => (int) $maxCc,
                    'price' => round((float) $price, 6),
                ];
            })
            ->filter()
            ->sortBy('max_cc')
            ->values();

        $duplicatedMaxCc = $normalizedTiers->pluck('max_cc')->duplicates()->first();
        if ($duplicatedMaxCc !== null) {
            throw ValidationException::withMessages([
                'price_tiers' => "La cilindrada {$duplicatedMaxCc}cc esta repetida.",
            ]);
        }

        $fallbackPrice = $normalizedTiers->isNotEmpty()
            ? (float) ($normalizedTiers->first()['price'] ?? 0)
            : 0;

        $validated['base_price'] = $validated['base_price'] !== null && $validated['base_price'] !== ''
            ? round((float) $validated['base_price'], 6)
            : $fallbackPrice;
        $validated['normalized_price_tiers'] = $normalizedTiers->all();

        return $validated;
    }

    private function syncPriceTiers(WorkshopService $service, array $tiers): void
    {
        $service->priceTiers()->delete();

        if (empty($tiers)) {
            return;
        }

        $service->priceTiers()->createMany(
            collect($tiers)
                ->values()
                ->map(fn ($tier, $index) => [
                    'max_cc' => (int) $tier['max_cc'],
                    'price' => round((float) $tier['price'], 6),
                    'order_num' => $index + 1,
                ])
                ->all()
        );
    }
}
