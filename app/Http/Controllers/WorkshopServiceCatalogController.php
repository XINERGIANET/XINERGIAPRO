<?php

namespace App\Http\Controllers;

use App\Models\WorkshopService;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'active' => (bool) ($validated['active'] ?? true),
            ]);

            $this->syncPriceTiers($service, $validated['normalized_price_tiers']);
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
                'active' => (bool) ($validated['active'] ?? false),
            ]);

            $this->syncPriceTiers($service, $validated['normalized_price_tiers']);
        });

        return back()->with('status', 'Servicio actualizado correctamente.');
    }

    public function destroy(WorkshopService $service): RedirectResponse
    {
        $this->assertServiceScope($service);
        $service->delete();

        return back()->with('status', 'Servicio eliminado correctamente.');
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
