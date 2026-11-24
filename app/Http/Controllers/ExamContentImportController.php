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
            $replace = $request->boolean('replace');
            $result = $this->import->importFromSyncedPack($request->user(), $set, $sub, $replace);

            return $this->safeJson([
                'ok' => true,
                'message' => 'Content imported into exam_content_* tables.',
                'data' => $result,
            ]);
        } catch (RuntimeException $e) {
            return $this->safeJson(['message' => $this->utf8Message($e->getMessage())], 422);
        }
    }

    public function summary(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [, $sub] = $this->resolve($exam, $subject);

            return $this->safeJson([
                'data' => $this->import->summary($request->user(), $sub),
            ]);
        } catch (RuntimeException $e) {
            return $this->safeJson(['message' => $this->utf8Message($e->getMessage())], 404);
        }
    }

    public function note(Request $request, string $exam, string $subject): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:255'],
        ]);

        try {
            [, $sub] = $this->resolve($exam, $subject);

            return $this->safeJson([
                'data' => $this->import->getNote($request->user(), $sub, $data['path']),
            ]);
        } catch (RuntimeException $e) {
            return $this->safeJson(['message' => $this->utf8Message($e->getMessage())], 404);
        }
    }

    public function chapter(Request $request, string $exam, string $subject, string $code): JsonResponse
    {
        try {
            [, $sub] = $this->resolve($exam, $subject);

            return $this->safeJson([
                'data' => $this->import->getChapter($request->user(), $sub, $code),
            ]);
        } catch (RuntimeException $e) {
            return $this->safeJson(['message' => $this->utf8Message($e->getMessage())], 404);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safeJson(array $payload, int $status = 200): JsonResponse
    {
        return response()->json(
            $payload,
            $status,
            [],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }

    private function utf8Message(string $message): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $message);

        return is_string($clean) && $clean !== '' ? $clean : 'Import failed.';
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
