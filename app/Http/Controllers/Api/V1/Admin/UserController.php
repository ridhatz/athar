<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\CreateUser;
use App\Actions\Admin\ResetUserPassword;
use App\Actions\Admin\UpdateUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetPasswordRequest;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserAdminResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Managing people through the API. The same Actions as the web screen; ADMIN only. */
class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::forUser($request->user())->authorize('viewAny', User::class);

        $users = User::query()->with(['roles', 'teams', 'department'])->orderBy('name')->paginate(50);

        return UserAdminResource::collection($users)->response();
    }

    public function store(StoreUserRequest $request, CreateUser $create): JsonResponse
    {
        [$user, $generated] = $create->handle($request->user(), $request->validated());

        return response()->json([
            'data' => UserAdminResource::make($user)->resolve(),
            // Shown once. It is not stored anywhere in plain text.
            'generated_password' => $generated,
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): UserAdminResource
    {
        return UserAdminResource::make($update->handle($request->user(), $user, $request->validated()));
    }

    public function resetPassword(ResetPasswordRequest $request, User $user, ResetUserPassword $reset): JsonResponse
    {
        return response()->json(['password' => $reset->handle($request->user(), $user, $request->validated('password'))]);
    }
}
