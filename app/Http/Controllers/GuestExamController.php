<?php

namespace App\Http\Controllers;

use App\Models\ExamApplication;
use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Services\ExamContentImportService;
use App\Services\ExamPracticeService;
use App\Services\GuestExamAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Guest-panel exam API.
 *  - any enabled exam can be listed and applied for
 *  - notes / practice sets / tests / content are readable only after the owner approves
 * Content comes from the owner's synced packs; attempts are saved for the guest.
 */
class GuestExamController extends Controller
{
    public function __construct(
        private GuestExamAccess $access,
        private ExamContentImportService $content,
        private ExamPracticeService $practice,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $guest = $request->user();

        $applications = ExamApplication::query()
            ->where('user_id', $guest->id)
            ->get()
            ->keyBy('exam_set_id');

        $data = ExamSet::query()
            ->with('subjects')
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ExamSet $exam) use ($applications) {
                /** @var ExamApplication|null $app */
                $app = $applications->get($exam->id);

                return [
                    'id' => $exam->id,
                    'slug' => $exam->slug,
                    'title' => $exam->title,
                    'year' => $exam->year,
                    'subjects' => $exam->subjects
                        ->where('is_enabled', true)
                        ->sortBy('sort_order')
                        ->values()
                        ->map(fn (ExamSubject $s) => ['slug' => $s->slug, 'title' => $s->title]),
                    'application' => $app ? $this->applicationPayload($app) : null,
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    public function apply(Request $request, string $exam): JsonResponse
    {
        $guest = $request->user();
        $set = $this->exam($exam);

        $app = $this->access->application($guest, $set);

        if ($app && in_array($app->status, [ExamApplication::PENDING, ExamApplication::APPROVED], true)) {
            return response()->json(['data' => $this->applicationPayload($app)]);
        }

        // New application, or re-apply after a rejection.
        $created = $app === null;
        $app ??= new ExamApplication(['user_id' => $guest->id, 'exam_set_id' => $set->id]);
        $app->status = ExamApplication::PENDING;
        $app->decided_at = null;
        $app->decided_by = null;
        $app->save();

        return response()->json(['data' => $this->applicationPayload($app)], $created ? 201 : 200);
    }

    /** Approved exam with its subjects (and what content each has). */
    public function show(Request $request, string $exam): JsonResponse
    {
        $set = $this->approvedExam($request, $exam);
        $owner = $this->owner();

        $states = ExamSubjectState::query()
            ->where('user_id', $owner->id)
            ->whereIn('exam_subject_id', $set->subjects->pluck('id'))
            ->get()
            ->keyBy('exam_subject_id');

        $subjects = $set->subjects
            ->where('is_enabled', true)
            ->sortBy('sort_order')
            ->values()
            ->map(function (ExamSubject $s) use ($owner, $states) {
                $totals = $this->content->summary($owner, $s)['totals'];

                return [
                    'slug' => $s->slug,
                    'title' => $s->title,
                    'has_pack' => filled($states->get($s->id)?->storage_path),
                    'chapters' => $totals['chapters'],
                    'questions' => $totals['questions'],
                    'notes' => $totals['notes'],
                ];
            });

        return $this->safeJson([
            'data' => [
                'id' => $set->id,
                'slug' => $set->slug,
                'title' => $set->title,
                'year' => $set->year,
                'subjects' => $subjects,
            ],
        ]);
    }

    public function summary(Request $request, string $exam, string $subject): JsonResponse
    {
        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => $this->content->summary($owner, $sub),
        ]);
    }

    public function note(Request $request, string $exam, string $subject): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string', 'max:255']]);

        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => $this->content->getNote($owner, $sub, $data['path']),
        ]);
    }

    public function chapter(Request $request, string $exam, string $subject, string $code): JsonResponse
    {
        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => $this->content->getChapter($owner, $sub, $code),
        ]);
    }

    public function sets(Request $request, string $exam, string $subject): JsonResponse
    {
        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => [
                'sets' => $this->practice->listSets($owner, $set, $sub),
                'attempts' => $this->practice->recentAttempts($request->user(), $sub, 5),
            ],
        ]);
    }

    public function showSet(Request $request, string $exam, string $subject, string $setKey): JsonResponse
    {
        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => $this->practice->getSet($owner, $set, $sub, $setKey),
        ]);
    }

    public function submit(Request $request, string $exam, string $subject, string $setKey): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:1'],
            'started_at' => ['nullable', 'date'],
        ]);

        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => $this->practice->submit(
                $request->user(),
                $set,
                $sub,
                $setKey,
                $data['answers'],
                $data['started_at'] ?? null,
                $owner,
            ),
        ], failStatus: 422);
    }

    public function performance(Request $request, string $exam, string $subject): JsonResponse
    {
        return $this->guarded($request, $exam, $subject, fn ($set, $sub, $owner) => [
            'data' => $this->practice->performance($request->user(), $sub, 50),
        ]);
    }

    /**
     * Approval gate + exam/subject lookup, then run $work and map RuntimeException to a JSON error.
     *
     * @param  callable(ExamSet, ExamSubject, \App\Models\User): array<string, mixed>  $work
     */
    private function guarded(Request $request, string $exam, string $subject, callable $work, int $failStatus = 404): JsonResponse
    {
        $set = $this->approvedExam($request, $exam);

        try {
            $sub = $set->subjects->first(fn (ExamSubject $s) => $s->slug === $subject && $s->is_enabled);
            if (! $sub) {
                throw new RuntimeException('Subject not found.');
            }

            return $this->safeJson($work($set, $sub, $this->access->owner()));
        } catch (RuntimeException $e) {
            return $this->safeJson(['message' => $this->utf8Message($e->getMessage())], $failStatus);
        }
    }

    private function exam(string $slug): ExamSet
    {
        $set = ExamSet::query()->with('subjects')->where('slug', $slug)->where('is_enabled', true)->first();
        if (! $set) {
            abort(404, 'Exam not found.');
        }

        return $set;
    }

    private function approvedExam(Request $request, string $slug): ExamSet
    {
        $set = $this->exam($slug);
        if (! $this->access->isApproved($request->user(), $set)) {
            abort(403, 'Your application for this exam is not approved yet.');
        }

        return $set;
    }

    private function owner(): \App\Models\User
    {
        try {
            return $this->access->owner();
        } catch (RuntimeException $e) {
            abort(404, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(ExamApplication $app): array
    {
        return [
            'id' => $app->id,
            'status' => $app->status,
            'applied_at' => $app->created_at?->toIso8601String(),
            'decided_at' => $app->decided_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function safeJson(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function utf8Message(string $message): string
    {
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $message);

        return is_string($clean) && $clean !== '' ? $clean : 'Request failed.';
    }
}
