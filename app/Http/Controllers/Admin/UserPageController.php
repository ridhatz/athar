<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateUser;
use App\Actions\Admin\ResetUserPassword;
use App\Actions\Admin\UpdateUser;
use App\Enums\RoleCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserAdminResource;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** The "People" screen. ADMIN only; the rules live in the Actions and UserPolicy. */
class UserPageController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser($request->user())->authorize('viewAny', User::class);

        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');

        $users = User::query()
            ->with(['roles', 'teams', 'department'])
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when(in_array($role, RoleCode::values(), true), fn ($query) => $query->whereHas('roles', fn ($r) => $r->where('code', $role)))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/users', [
            'users' => UserAdminResource::collection($users),
            'filters' => ['q' => $q, 'role' => in_array($role, RoleCode::values(), true) ? $role : ''],
            'roles' => RoleCode::values(),
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUser $create): RedirectResponse
    {
        [$user, $generated] = $create->handle($request->user(), $request->validated());

        // Shown once, on the next page, and never kept.
        Inertia::flash('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $generated]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.created', ['name' => $user->name])]);

        return back();
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): RedirectResponse
    {
        $update->handle($request->user(), $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.updated')]);

        return back();
    }

    public function resetPassword(ResetPasswordRequest $request, User $user, ResetUserPassword $reset): RedirectResponse
    {
        $plain = $reset->handle($request->user(), $user, $request->validated('password'));

        Inertia::flash('credentials', ['name' => $user->name, 'email' => $user->email, 'password' => $plain]);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('admin.flash.reset')]);

        return back();
    }
}
