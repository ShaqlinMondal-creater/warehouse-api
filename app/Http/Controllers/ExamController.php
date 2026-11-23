<?php

namespace App\Http\Controllers;

use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Services\ExamPackSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExamController extends Controller
{
    public function __construct(private ExamPackSyncService $packs) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $sets = ExamSet::query()
            ->with('subjects')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $subjectIds = $sets->flatMap->subjects->pluck('id');
        $states = ExamSubjectState::query()
            ->where('user_id', $userId)
            ->whereIn('exam_subject_id', $subjectIds)
            ->get()
            ->keyBy('exam_subject_id');

        return response()->json([
            'data' => $sets->map(fn (ExamSet $exam) => $this->serializeExam($exam, $states))->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['title']);
        if ($slug === '') {
            return response()->json(['message' => 'Could not build a slug from title.'], 422);
        }
        if (ExamSet::query()->where('slug', $slug)->exists()) {
            return response()->json(['message' => 'An exam with this slug already exists.'], 422);
        }

        $exam = ExamSet::query()->create([
            'title' => $data['title'],
            'slug' => $slug,
            'year' => $data['year'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) ExamSet::query()->max('sort_order') + 1),
        ]);

        return response()->json(['data' => $this->serializeExam($exam->load('subjects'))], 201);
    }

    public function show(Request $request, string $exam): JsonResponse
    {
        $set = $this->findExam($exam, requireEnabled: false);
        $userId = $request->user()->id;

        $states = ExamSubjectState::query()
            ->where('user_id', $userId)
            ->whereIn('exam_subject_id', $set->subjects->pluck('id'))
            ->get()
            ->keyBy('exam_subject_id');

        return response()->json(['data' => $this->serializeExam($set, $states)]);
    }

    public function update(Request $request, string $exam): JsonResponse
    {
        $set = $this->findExam($exam, requireEnabled: false);
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:80', 'alpha_dash'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['slug']) && $data['slug'] !== $set->slug) {
            if (ExamSet::query()->where('slug', $data['slug'])->where('id', '!=', $set->id)->exists()) {
                return response()->json(['message' => 'An exam with this slug already exists.'], 422);
            }
        }

        $set->update($data);

        return response()->json(['data' => $this->serializeExam($set->fresh('subjects'))]);
    }

    public function destroy(Request $request, string $exam): JsonResponse
    {
        $set = $this->findExam($exam, requireEnabled: false);
        $userId = $request->user()->id;

        foreach ($set->subjects as $subject) {
            $this->purgeSubjectStorage($userId, $set->slug, $subject->slug);
        }

        $set->delete();

        return response()->json(['ok' => true]);
    }

    public function storeSubject(Request $request, string $exam): JsonResponse
    {
        $set = $this->findExam($exam, requireEnabled: false);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $slug = $data['slug'] ?? Str::slug($data['title']);
        if ($slug === '') {
            return response()->json(['message' => 'Could not build a subject slug from title.'], 422);
        }
        if ($set->subjects()->where('slug', $slug)->exists()) {
            return response()->json(['message' => 'A subject with this slug already exists.'], 422);
        }

        $subject = $set->subjects()->create([
            'title' => $data['title'],
            'slug' => $slug,
            'is_enabled' => $data['is_enabled'] ?? true,
            'sort_order' => $data['sort_order'] ?? ((int) $set->subjects()->max('sort_order') + 1),
        ]);

        return response()->json(['data' => $this->serializeSubject($subject)], 201);
    }

    public function updateSubject(Request $request, string $exam, string $subject): JsonResponse
    {
        $set = $this->findExam($exam, requireEnabled: false);
        $sub = $this->findSubjectOnExam($set, $subject, requireEnabled: false);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'slug' => ['sometimes', 'string', 'max:80', 'alpha_dash'],
            'is_enabled' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (isset($data['slug']) && $data['slug'] !== $sub->slug) {
            if ($set->subjects()->where('slug', $data['slug'])->where('id', '!=', $sub->id)->exists()) {
                return response()->json(['message' => 'A subject with this slug already exists.'], 422);
            }
        }

        $sub->update($data);

        return response()->json(['data' => $this->serializeSubject($sub->fresh())]);
    }

    public function destroySubject(Request $request, string $exam, string $subject): JsonResponse
    {
        $set = $this->findExam($exam, requireEnabled: false);
        $sub = $this->findSubjectOnExam($set, $subject, requireEnabled: false);

        $this->purgeSubjectStorage($request->user()->id, $set->slug, $sub->slug);
        $sub->delete();

        return response()->json(['ok' => true]);
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
            'pack' => ['required', 'file', 'max:51200'],
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

    /** Remove extracted zip pack for this subject (keeps the subject row). */
    public function clearPack(Request $request, string $exam, string $subject): JsonResponse
    {
        try {
            [$set, $sub] = $this->findExamSubject($exam, $subject, requireEnabled: false);
            $this->packs->clearSubjectPack($request->user(), $set, $sub);

            return response()->json([
                'ok' => true,
                'data' => $this->packs->subjectSummary($request->user(), $set, $sub),
                'message' => 'Uploads removed.',
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ExamSubjectState>|null  $states
     * @return array<string, mixed>
     */
    private function serializeExam(ExamSet $exam, $states = null): array
    {
        return [
            'id' => $exam->id,
            'slug' => $exam->slug,
            'title' => $exam->title,
            'year' => $exam->year,
            'is_enabled' => $exam->is_enabled,
            'sort_order' => $exam->sort_order,
            'subjects' => $exam->subjects
                ->sortBy('sort_order')
                ->values()
                ->map(function (ExamSubject $subject) use ($states) {
                    $state = $states?->get($subject->id);

                    return $this->serializeSubject($subject, $state);
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSubject(ExamSubject $subject, ?ExamSubjectState $state = null): array
    {
        return [
            'id' => $subject->id,
            'slug' => $subject->slug,
            'title' => $subject->title,
            'is_enabled' => $subject->is_enabled,
            'sort_order' => $subject->sort_order,
            'synced' => filled($state?->storage_path),
            'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
            'last_original_name' => $state?->last_original_name,
            'file_count' => $state?->file_count ?? 0,
        ];
    }

    private function findExam(string $exam, bool $requireEnabled = true): ExamSet
    {
        $query = ExamSet::query()->with('subjects')->where('slug', $exam);
        if ($requireEnabled) {
            $query->where('is_enabled', true);
        }
        $set = $query->first();
        if (! $set) {
            abort(404, 'Exam not found.');
        }

        return $set;
    }

    private function findSubjectOnExam(ExamSet $set, string $subject, bool $requireEnabled = true): ExamSubject
    {
        $sub = $set->subjects->first(function (ExamSubject $s) use ($subject, $requireEnabled) {
            if ($s->slug !== $subject) {
                return false;
            }

            return $requireEnabled ? $s->is_enabled : true;
        });

        if (! $sub) {
            throw new RuntimeException('Subject not found.');
        }

        return $sub;
    }

    /**
     * @return array{0: ExamSet, 1: ExamSubject}
     */
    private function findExamSubject(string $exam, string $subject, bool $requireEnabled = true): array
    {
        $set = $this->findExam($exam, requireEnabled: $requireEnabled);
        $sub = $this->findSubjectOnExam($set, $subject, requireEnabled: $requireEnabled);

        return [$set, $sub];
    }

    private function purgeSubjectStorage(int $userId, string $examSlug, string $subjectSlug): void
    {
        $dir = storage_path('app/private/exams/'.$userId.'/'.$examSlug.'/'.$subjectSlug);
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }
}
