<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateDepartment;
use App\Actions\Admin\UpdateDepartment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentAdminResource;
use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The "Departments" screen. ADMIN only; the rules live in the Actions and DepartmentPolicy. */
class DepartmentPageController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', Department::class);

        $q = trim((string) $request->query('q', ''));

        $departments = Department::query()
            ->withCount('users')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', '%'.addcslashes($q, '%_\\').'%');
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/departments', [
            'departments' => DepartmentAdminResource::collection($departments),
            'filters' => ['q' => $q],
        ]);
    }

    public function store(StoreDepartmentRequest $request, CreateDepartment $create): RedirectResponse
    {
        $department = $create->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.department_created', ['name' => $department->name])]);

        return back();
    }

    public function update(UpdateDepartmentRequest $request, Department $department, UpdateDepartment $update): RedirectResponse
    {
        $update->handle($request->user(), $department, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.updated')]);

        return back();
    }
}
