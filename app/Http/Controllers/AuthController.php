<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var User|null $user */
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Keep each account type on its own login page (checked after the password).
        $portal = $data['portal'] ?? 'owner';
        $isGuest = $user->role === 'guest';
        if ($portal === 'guest' && ! $isGuest) {
            throw ValidationException::withMessages([
                'email' => ['This is not a guest account. Use the main login page.'],
            ]);
        }
        if ($portal !== 'guest' && $isGuest) {
            throw ValidationException::withMessages([
                'email' => ['Guest accounts must use the guest login page.'],
            ]);
        }

        $token = $user->createToken($data['device_name'] ?? 'warehouse-web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar_initials' => $user->avatar_initials,
        ];
    }
}
