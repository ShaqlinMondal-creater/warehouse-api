<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Owner-side management of guest accounts.
 * Guests live in the same `users` table with role = "guest".
 */
class GuestController extends Controller
{
    public function index(): JsonResponse
    {
        $items = User::query()
            ->where('role', 'guest')
            ->orderByDesc('id')
            ->get()
            ->map(fn (User $user) => $this->payload($user))
            ->values();

        return response()->json(['data' => $items]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $name = trim($data['name']);

        $guest = User::query()->create([
            'name' => $name,
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'guest',
            'avatar_initials' => $this->initials($name),
        ]);

        return response()->json(['data' => $this->payload($guest)], 201);
    }

    public function update(Request $request, int $guest): JsonResponse
    {
        $user = $this->findGuest($guest);
        $this->normalizeEmail($request);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:100'],
        ]);

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            $user->name = $name;
            $user->avatar_initials = $this->initials($name);
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }

        $passwordChanged = ! empty($data['password']);
        if ($passwordChanged) {
            $user->password = $data['password'];
        }

        DB::transaction(function () use ($user, $passwordChanged) {
            $user->save();
            if ($passwordChanged) {
                // Sign the guest out everywhere after a password reset.
                $user->tokens()->delete();
            }
        });

        return response()->json(['data' => $this->payload($user->fresh())]);
    }

    public function destroy(int $guest): JsonResponse
    {
        $user = $this->findGuest($guest);

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['ok' => true]);
    }

    private function findGuest(int $id): User
    {
        return User::query()->where('role', 'guest')->findOrFail($id);
    }

    private function normalizeEmail(Request $request): void
    {
        if ($request->has('email')) {
            $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        }
    }

    private function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $letters .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $letters !== '' ? $letters : 'G';
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
}
