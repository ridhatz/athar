<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** API tokens live for half a day; clients sign in again after that. */
    private const TOKEN_HOURS = 12;

    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        // Always run a hash check so an unknown email costs the same time as a wrong password.
        $passwordOk = Hash::check($data['password'], $user?->password ?? Hash::make(Str::random(32)));

        if ($user === null || ! $passwordOk || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        // A password alone would sidestep a second factor, so those accounts cannot mint tokens here.
        if ($user->two_factor_confirmed_at !== null) {
            return response()->json(['message' => __('errors.api_token_needs_no_2fa')], 403);
        }

        $token = $user->createToken($data['device_name'] ?? 'api', ['*'], now()->addHours(self::TOKEN_HOURS));

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => UserResource::make($user->load('department', 'teams')),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('department', 'teams'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'ok']);
    }
}
