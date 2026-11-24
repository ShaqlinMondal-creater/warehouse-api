<?php

namespace App\Http\Controllers;

use App\Models\ExamApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Owner-side review of guest exam applications (approve / reject / revoke).
 */
class ExamApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([ExamApplication::PENDING, ExamApplication::APPROVED, ExamApplication::REJECTED])],
        ]);

        $items = ExamApplication::query()
            ->with(['user:id,name,email', 'exam:id,slug,title'])
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->get()
            ->map(fn (ExamApplication $app) => $this->payload($app))
            ->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'pending' => ExamApplication::query()->where('status', ExamApplication::PENDING)->count(),
            ],
        ]);
    }

    public function update(Request $request, int $application): JsonResponse
    {
        $app = ExamApplication::query()->with(['user:id,name,email', 'exam:id,slug,title'])->findOrFail($application);

        $data = $request->validate([
            'status' => ['required', Rule::in([ExamApplication::PENDING, ExamApplication::APPROVED, ExamApplication::REJECTED])],
        ]);

        $pending = $data['status'] === ExamApplication::PENDING;
        $app->update([
            'status' => $data['status'],
            'decided_at' => $pending ? null : now(),
            'decided_by' => $pending ? null : $request->user()->id,
        ]);

        return response()->json(['data' => $this->payload($app->fresh(['user:id,name,email', 'exam:id,slug,title']))]);
    }

    public function destroy(int $application): JsonResponse
    {
        ExamApplication::query()->findOrFail($application)->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ExamApplication $app): array
    {
        return [
            'id' => $app->id,
            'status' => $app->status,
            'applied_at' => $app->created_at?->toIso8601String(),
            'decided_at' => $app->decided_at?->toIso8601String(),
            'guest' => $app->user ? [
                'id' => $app->user->id,
                'name' => $app->user->name,
                'email' => $app->user->email,
            ] : null,
            'exam' => $app->exam ? [
                'id' => $app->exam->id,
                'slug' => $app->exam->slug,
                'title' => $app->exam->title,
            ] : null,
        ];
    }
}
