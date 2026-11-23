<?php

namespace App\Http\Controllers;

use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Services\ExamPackSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class ExamController extends Controller
{
    public function __construct(private ExamPackSyncService $packs) {}

    /** Bootstrap default exam catalog when empty (live-safe, idempotent). */
    public function setup(Request $request): JsonResponse
    {
        $this->ensureDefaultCatalog();

        return $this->index($request);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $sets = ExamSet::query()
            ->with(['subjects' => fn ($q) => $q->where('is_enabled', true)])
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $subjectIds = $sets->flatMap->subjects->pluck('id');
        $states = ExamSubjectState::query()
            ->where('user_id', $userId)
            ->whereIn('exam_subject_id', $subjectIds)
            ->get()
            ->keyBy('exam_subject_id');

        $data = $sets->map(function (ExamSet $exam) use ($states) {
            return [
                'id' => $exam->id,
                'slug' => $exam->slug,
                'title' => $exam->title,
                'year' => $exam->year,
                'is_enabled' => $exam->is_enabled,
                'subjects' => $exam->subjects->map(function (ExamSubject $subject) use ($states) {
                    $state = $states->get($subject->id);

                    return [
                        'id' => $subject->id,
                        'slug' => $subject->slug,
                        'title' => $subject->title,
                        'synced' => filled($state?->storage_path),
                        'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
                        'file_count' => $state?->file_count ?? 0,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $exam): JsonResponse
    {
        $set = $this->findExam($exam);
        $userId = $request->user()->id;

        $states = ExamSubjectState::query()
            ->where('user_id', $userId)
            ->whereIn('exam_subject_id', $set->subjects->pluck('id'))
            ->get()
            ->keyBy('exam_subject_id');

        return response()->json([
            'data' => [
                'id' => $set->id,
                'slug' => $set->slug,
                'title' => $set->title,
                'year' => $set->year,
                'is_enabled' => $set->is_enabled,
                'subjects' => $set->subjects->where('is_enabled', true)->values()->map(function (ExamSubject $subject) use ($states) {
                    $state = $states->get($subject->id);

                    return [
                        'id' => $subject->id,
                        'slug' => $subject->slug,
                        'title' => $subject->title,
                        'synced' => filled($state?->storage_path),
                        'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
                        'last_original_name' => $state?->last_original_name,
                        'file_count' => $state?->file_count ?? 0,
                    ];
                }),
            ],
        ]);
    }

    public function subject(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [$set, $sub] = $this->findExamSubject($exam, $subject);

            return response()->json([
                'data' => $this->packs->subjectSummary($request->user(), $set, $sub),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function sync(Request $request, string $exam, string $subject): JsonResponse
    {
        $request->validate([
            'pack' => ['required', 'file', 'max:51200'], // KB → 50 MB
        ]);

        try {
            [$set, $sub] = $this->findExamSubject($exam, $subject);
            $sync = $this->packs->syncSubjectZip(
                $request->user(),
                $set,
                $sub,
                $request->file('pack'),
            );

            $status = $sync->status === 'ok' ? 200 : 422;

            return response()->json([
                'data' => [
                    'sync' => $sync,
                    'summary' => $sync->status === 'ok'
                        ? $this->packs->subjectSummary($request->user(), $set, $sub)
                        : null,
                ],
                'message' => $sync->message,
            ], $status);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Sync failed.'], 500);
        }
    }

    private function ensureDefaultCatalog(): void
    {
        $clerkship = ExamSet::query()->updateOrCreate(
            ['slug' => 'clerkship-2026'],
            [
                'title' => 'Clerkship 2026',
                'year' => 2026,
                'is_enabled' => true,
                'sort_order' => 1,
            ],
        );

        $clerkship->subjects()->updateOrCreate(
            ['slug' => 'general-studies'],
            [
                'title' => 'General Studies',
                'is_enabled' => true,
                'sort_order' => 1,
            ],
        );

        $clerkship->subjects()->updateOrCreate(
            ['slug' => 'mathematics'],
            [
                'title' => 'Mathematics',
                'is_enabled' => false,
                'sort_order' => 2,
            ],
        );

        $clerkship->subjects()->updateOrCreate(
            ['slug' => 'english'],
            [
                'title' => 'English',
                'is_enabled' => false,
                'sort_order' => 3,
            ],
        );

        ExamSet::query()->updateOrCreate(
            ['slug' => 'ido-2026'],
            [
                'title' => 'IDO 2026',
                'year' => 2026,
                'is_enabled' => true,
                'sort_order' => 2,
            ],
        );
    }

    private function findExam(string $exam): ExamSet
    {
        $set = ExamSet::query()
            ->with('subjects')
            ->where('slug', $exam)
            ->where('is_enabled', true)
            ->first();

        if (! $set) {
            abort(404, 'Exam not found.');
        }

        return $set;
    }

    /**
     * @return array{0: ExamSet, 1: ExamSubject}
     */
    private function findExamSubject(string $exam, string $subject): array
    {
        $set = $this->findExam($exam);
        $sub = $set->subjects->first(fn (ExamSubject $s) => $s->slug === $subject && $s->is_enabled);
        if (! $sub) {
            throw new RuntimeException('Subject not found.');
        }

        return [$set, $sub];
    }
}
