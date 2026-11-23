<?php

namespace App\Http\Controllers;

use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Services\ExamPracticeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExamPracticeController extends Controller
{
    public function __construct(private ExamPracticeService $practice) {}

    public function sets(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [$set, $sub] = $this->resolve($exam, $subject);

            return response()->json([
                'data' => [
                    'sets' => $this->practice->listSets($request->user(), $set, $sub),
                    'attempts' => $this->practice->recentAttempts($request->user(), $sub, 5),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function showSet(Request $request, string $exam, string $subject, string $setKey): JsonResponse
    {
        try {
            [$set, $sub] = $this->resolve($exam, $subject);

            return response()->json([
                'data' => $this->practice->getSet($request->user(), $set, $sub, $setKey),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function submit(Request $request, string $exam, string $subject, string $setKey): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:1'],
            'started_at' => ['nullable', 'date'],
        ]);

        try {
            [$set, $sub] = $this->resolve($exam, $subject);
            $result = $this->practice->submit(
                $request->user(),
                $set,
                $sub,
                $setKey,
                $data['answers'],
                $data['started_at'] ?? null,
            );

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function performance(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [, $sub] = $this->resolve($exam, $subject);

            return response()->json([
                'data' => $this->practice->performance($request->user(), $sub, 50),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @return array{0: ExamSet, 1: ExamSubject}
     */
    private function resolve(string $exam, string $subject): array
    {
        $set = ExamSet::query()->with('subjects')->where('slug', $exam)->where('is_enabled', true)->first();
        if (! $set) {
            throw new RuntimeException('Exam not found.');
        }
        $sub = $set->subjects->first(fn (ExamSubject $s) => $s->slug === $subject && $s->is_enabled);
        if (! $sub) {
            throw new RuntimeException('Subject not found.');
        }

        return [$set, $sub];
    }
}
