<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/** The signed-in guest reads / edits their own profile. */
class GuestProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->payload($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'avatar_initials' => ['sometimes', 'nullable', 'string', 'max:8'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
        ]);

        $changingPassword = filled($data['password'] ?? null);
        if ($changingPassword && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (isset($data['name'])) {
            $user->name = trim($data['name']);
        }
        if (isset($data['email'])) {
            $user->email = $data['email'];
        }
        if (array_key_exists('avatar_initials', $data)) {
            $custom = Str::upper(trim((string) $data['avatar_initials']));
            $user->avatar_initials = $custom !== '' ? $custom : $this->initials($user->name);
        } elseif (isset($data['name'])) {
            $user->avatar_initials = $this->initials($user->name);
        }
        if ($changingPassword) {
            $user->password = $data['password'];
        }
        $user->save();

        if ($changingPassword) {
            // Sign out every other device; keep the one making this request.
            $current = $user->currentAccessToken();
            $keep = $current instanceof PersonalAccessToken ? $current->id : null;
            $user->tokens()->when($keep, fn ($q) => $q->where('id', '!=', $keep))->delete();
        }

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'avatar_initials' => $user->avatar_initials,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : 'G';
    }
}
