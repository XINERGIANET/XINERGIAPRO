<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\WorkshopAdditionalAccessory;
use App\Support\WorkshopAuthorization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkshopAdditionalAccessoryController extends Controller
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
        [$branchId, $companyId] = $this->branchScope();
        $search = trim((string) $request->input('search', ''));
        $perPage = (int) $request->input('per_page', 20);

        $accessories = WorkshopAdditionalAccessory::query()
            ->where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('order_num')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('workshop.accessories.index', compact('accessories', 'search', 'perPage'));
    }

    public function store(Request $request): RedirectResponse
    {
        [$branchId, $companyId] = $this->branchScope();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('workshop_additional_accessories', 'name')
                    ->where(fn ($query) => $query->where('branch_id', $branchId))
                    ->whereNull('deleted_at'),
            ],
            'order_num' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        WorkshopAdditionalAccessory::query()->create([
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'name' => trim((string) $validated['name']),
            'order_num' => (int) ($validated['order_num'] ?? 0),
            'active' => (bool) ($validated['active'] ?? true),
        ]);

        return back()->with('status', 'Accesorio adicional registrado correctamente.');
    }

    public function update(Request $request, WorkshopAdditionalAccessory $accessory): RedirectResponse
    {
        $this->assertAccessoryScope($accessory);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('workshop_additional_accessories', 'name')
                    ->ignore($accessory->id)
                    ->where(fn ($query) => $query->where('branch_id', (int) $accessory->branch_id))
                    ->whereNull('deleted_at'),
            ],
            'order_num' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);

        $accessory->update([
            'name' => trim((string) $validated['name']),
            'order_num' => (int) ($validated['order_num'] ?? 0),
            'active' => (bool) ($validated['active'] ?? false),
        ]);

        return back()->with('status', 'Accesorio adicional actualizado correctamente.');
    }

    public function destroy(WorkshopAdditionalAccessory $accessory): RedirectResponse
    {
        $this->assertAccessoryScope($accessory);
        $accessory->delete();

        return back()->with('status', 'Accesorio adicional eliminado correctamente.');
    }

    private function branchScope(): array
    {
        $branchId = (int) session('branch_id');
        $branch = Branch::query()->findOrFail($branchId);

        return [$branchId, (int) $branch->company_id];
    }

    private function assertAccessoryScope(WorkshopAdditionalAccessory $accessory): void
    {
        [$branchId, $companyId] = $this->branchScope();

        if ((int) $accessory->branch_id !== $branchId || (int) $accessory->company_id !== $companyId) {
            abort(404);
        }
    }
}
