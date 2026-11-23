<?php

namespace App\Http\Controllers;

use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Services\ExamContentImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Separate content-bank import (does not replace zip sync or practice file player).
 */
class ExamContentImportController extends Controller
{
    public function __construct(private ExamContentImportService $import) {}

    public function import(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [$set, $sub] = $this->resolve($exam, $subject);
            $result = $this->import->importFromSyncedPack($request->user(), $set, $sub);

            return response()->json([
                'ok' => true,
                'message' => 'Content imported into exam_content_* tables.',
                'data' => $result,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function summary(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [, $sub] = $this->resolve($exam, $subject);

            return response()->json([
                'data' => $this->import->summary($request->user(), $sub),
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
