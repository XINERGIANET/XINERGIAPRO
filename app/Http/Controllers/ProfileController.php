<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Operation;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);
        $allowedPerPage = [10, 20, 50, 100];
        $viewId = $request->input('view_id');
        $branchId = $request->session()->get('branch_id');
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
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $profiles = Profile::query()
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'ILIKE', "%{$search}%");
            })
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('profiles.index', [
            'profiles' => $profiles,
            'search' => $search,
            'perPage' => $perPage,
            'operaciones' => $operaciones,
            'title' => 'Perfiles',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProfile($request);

        DB::transaction(function () use ($data) {
            $profile = Profile::create($data);
            $branchIds = Branch::query()->pluck('id');

            if ($branchIds->isNotEmpty()) {
                $now = now();
                $rows = $branchIds->map(fn ($branchId) => [
                    'profile_id' => $profile->id,
                    'branch_id' => $branchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('profile_branch')->insert($rows->all());
            }
        });

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.profiles.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Perfil creado correctamente.');
    }

    public function edit(Profile $profile)
    {
        return view('profiles.edit', [
            'profile' => $profile,
            'title' => 'Perfiles',
        ]);
    }

    public function update(Request $request, Profile $profile)
    {
        $data = $this->validateProfile($request);
        $profile->update($data);

        $viewId = $request->input('view_id');

        return redirect()
            ->route('admin.profiles.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Perfil actualizado correctamente.');
    }

    public function destroy(Profile $profile)
    {
        $profile->delete();

        $viewId = request('view_id');

        return redirect()
            ->route('admin.profiles.index', $viewId ? ['view_id' => $viewId] : [])
            ->with('status', 'Perfil eliminado correctamente.');
    }

    private function validateProfile(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
