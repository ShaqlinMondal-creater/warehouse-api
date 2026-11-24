<?php

namespace App\Services;

use App\Models\ExamContentChapter;
use App\Models\ExamContentNote;
use App\Models\ExamContentQuestion;
use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Models\User;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Separate from zip sync: reads an already-synced pack folder and upserts
 * chapters / questions(+answers) / notes into exam_content_* tables.
 */
class ExamContentImportService
{
    public function __construct(private ExamPackSetFinder $setFinder) {}

    /**
     * @return array<string, mixed>
     */
    public function importFromSyncedPack(User $user, ExamSet $exam, ExamSubject $subject): array
    {
        $dir = $this->packDir($user, $exam, $subject);

        $stats = [
            'chapters' => ['inserted' => 0, 'updated' => 0, 'unchanged' => 0],
            'questions' => ['inserted' => 0, 'updated' => 0, 'unchanged' => 0],
            'notes' => ['inserted' => 0, 'updated' => 0, 'unchanged' => 0],
            'sets_scanned' => 0,
        ];

        $this->importChapters($user, $subject, $dir, $stats);
        $this->importNotes($user, $subject, $dir, $stats);
        $this->importPracticeSets($user, $subject, $dir, $stats);

        $stats['totals'] = $this->summaryCounts($user, $subject);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user, ExamSubject $subject): array
    {
        $chapterQuestionCounts = ExamContentQuestion::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->selectRaw('COALESCE(chapter_code, ?) as chapter_code, COUNT(*) as question_count', ['Unknown'])
            ->groupBy('chapter_code')
            ->pluck('question_count', 'chapter_code');

        $chapters = ExamContentChapter::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->orderBy('code')
            ->get(['code', 'title', 'status', 'mcq_used', 'mcq_target'])
            ->map(function (ExamContentChapter $ch) use ($chapterQuestionCounts) {
                return [
                    'code' => $ch->code,
                    'title' => $ch->title,
                    'status' => $ch->status,
                    'mcq_used' => $ch->mcq_used,
                    'mcq_target' => $ch->mcq_target,
                    'question_count' => (int) ($chapterQuestionCounts[$ch->code] ?? 0),
                ];
            })
            ->values()
            ->all();

        // Include chapter codes that appear on questions but not in progress.json
        foreach ($chapterQuestionCounts as $code => $count) {
            $code = (string) $code;
            $exists = false;
            foreach ($chapters as $row) {
                if ($row['code'] === $code) {
                    $exists = true;
                    break;
                }
            }
            if (! $exists) {
                $chapters[] = [
                    'code' => $code,
                    'title' => null,
                    'status' => null,
                    'mcq_used' => null,
                    'mcq_target' => null,
                    'question_count' => (int) $count,
                ];
            }
        }

        usort($chapters, fn ($a, $b) => strcmp((string) $a['code'], (string) $b['code']));

        $notes = ExamContentNote::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->orderBy('relative_path')
            ->get(['relative_path', 'title', 'updated_at'])
            ->map(fn (ExamContentNote $n) => [
                'relative_path' => $n->relative_path,
                'title' => $n->title,
                'updated_at' => $n->updated_at?->toIso8601String(),
            ])
            ->all();

        return [
            'totals' => $this->summaryCounts($user, $subject),
            'sets' => ExamContentQuestion::query()
                ->where('user_id', $user->id)
                ->where('exam_subject_id', $subject->id)
                ->whereNotNull('set_key')
                ->selectRaw('set_key, COUNT(*) as question_count')
                ->groupBy('set_key')
                ->orderBy('set_key')
                ->get()
                ->map(fn ($r) => [
                    'set_key' => $r->set_key,
                    'question_count' => (int) $r->question_count,
                ])
                ->all(),
            'chapters' => $chapters,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function importChapters(User $user, ExamSubject $subject, string $dir, array &$stats): void
    {
        $progressPath = $dir.DIRECTORY_SEPARATOR.'progress.json';
        if (! is_file($progressPath)) {
            return;
        }

        $progress = json_decode($this->readUtf8File($progressPath), true);
        if (! is_array($progress) || ! isset($progress['chapters']) || ! is_array($progress['chapters'])) {
            return;
        }

        foreach ($progress['chapters'] as $code => $raw) {
            $code = $this->utf8(trim((string) $code));
            if ($code === '') {
                continue;
            }
            $meta = is_array($raw) ? $this->utf8Deep($raw) : ['value' => $raw];
            $payload = [
                'title' => isset($meta['title']) ? $this->utf8((string) $meta['title']) : null,
                'status' => isset($meta['status']) ? $this->utf8((string) $meta['status']) : null,
                'mcq_used' => isset($meta['mcqUsed']) ? (int) $meta['mcqUsed'] : (isset($meta['mcq_used']) ? (int) $meta['mcq_used'] : null),
                'mcq_target' => isset($meta['mcqTarget']) ? (int) $meta['mcqTarget'] : (isset($meta['mcq_target']) ? (int) $meta['mcq_target'] : null),
                'meta' => $meta,
            ];
            $hash = $this->contentHash($payload);

            $existing = ExamContentChapter::query()
                ->where('user_id', $user->id)
                ->where('exam_subject_id', $subject->id)
                ->where('code', $code)
                ->first();

            if ($existing && $existing->content_hash === $hash) {
                $stats['chapters']['unchanged']++;
                continue;
            }

            ExamContentChapter::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'exam_subject_id' => $subject->id,
                    'code' => $code,
                ],
                [
                    ...$payload,
                    'content_hash' => $hash,
                ],
            );

            if ($existing) {
                $stats['chapters']['updated']++;
            } else {
                $stats['chapters']['inserted']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function importNotes(User $user, ExamSubject $subject, string $dir, array &$stats): void
    {
        $notesDir = $dir.DIRECTORY_SEPARATOR.'notes';
        if (! is_dir($notesDir)) {
            return;
        }

        foreach (File::allFiles($notesDir) as $file) {
            $ext = strtolower($file->getExtension());
            if (! in_array($ext, ['md', 'txt'], true)) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = 'notes/'.str_replace('\\', '/', $file->getRelativePathname());
            $body = $this->readUtf8File($absolute);
            $hash = hash('sha256', $body);
            $title = $this->utf8($this->noteTitle($body, $file->getFilenameWithoutExtension()));

            $existing = ExamContentNote::query()
                ->where('user_id', $user->id)
                ->where('exam_subject_id', $subject->id)
                ->where('relative_path', $relative)
                ->first();

            if ($existing && $existing->content_hash === $hash) {
                $stats['notes']['unchanged']++;
                continue;
            }

            ExamContentNote::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'exam_subject_id' => $subject->id,
                    'relative_path' => $relative,
                ],
                [
                    'title' => $title,
                    'body' => $body,
                    'content_hash' => $hash,
                ],
            );

            if ($existing) {
                $stats['notes']['updated']++;
            } else {
                $stats['notes']['inserted']++;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function importPracticeSets(User $user, ExamSubject $subject, string $dir, array &$stats): void
    {
        foreach ($this->setFinder->list($dir) as $source) {
            $stats['sets_scanned']++;

            $setKey = $source['key'];
            $qRel = $source['questions_rel'];
            $aRel = $source['answers_rel'];

            $parsed = $this->setFinder->parse($source);
            $questions = $parsed['questions'];
            $answers = $parsed['answers'];

            foreach ($questions as $q) {
                // GS sets keep their own question ids; numbered (math) files use set#no.
                $externalId = $source['kind'] === 'day'
                    ? $this->utf8(trim((string) $q['id']))
                    : $this->utf8($setKey.'#'.(int) $q['no']);
                if ($externalId === '') {
                    continue;
                }

                $no = (int) $q['no'];
                $answerRow = $answers[$no] ?? null;
                $options = $this->utf8Deep($q['options'] ?? []);
                $payload = [
                    'set_key' => $setKey,
                    'question_no' => $no,
                    'chapter_code' => isset($q['chapter']) && $q['chapter'] !== null
                        ? $this->utf8((string) $q['chapter'])
                        : null,
                    'stem' => $this->utf8((string) $q['stem']),
                    'options' => is_array($options) ? $options : [],
                    'correct_answer' => $answerRow['answer'] ?? null,
                    'explanation' => isset($answerRow['explanation'])
                        ? $this->utf8((string) $answerRow['explanation'])
                        : null,
                    'source_questions_file' => $qRel,
                    'source_answers_file' => $aRel,
                ];
                $hash = $this->contentHash($payload);

                $existing = ExamContentQuestion::query()
                    ->where('user_id', $user->id)
                    ->where('exam_subject_id', $subject->id)
                    ->where('external_id', $externalId)
                    ->first();

                if ($existing && $existing->content_hash === $hash) {
                    $stats['questions']['unchanged']++;
                    continue;
                }

                ExamContentQuestion::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'exam_subject_id' => $subject->id,
                        'external_id' => $externalId,
                    ],
                    [
                        ...$payload,
                        'content_hash' => $hash,
                    ],
                );

                if ($existing) {
                    $stats['questions']['updated']++;
                } else {
                    $stats['questions']['inserted']++;
                }
            }
        }
    }

    /**
     * @return array{relative_path:string,title:string|null,body:string,updated_at:string|null}
     */
    public function getNote(User $user, ExamSubject $subject, string $relativePath): array
    {
        $path = str_replace('\\', '/', trim($relativePath));
        $note = ExamContentNote::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->where('relative_path', $path)
            ->first();

        if (! $note) {
            throw new RuntimeException('Note not found in DB bank. Import content first.');
        }

        return [
            'relative_path' => $note->relative_path,
            'title' => $note->title,
            'body' => $note->body,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Chapter study pack: matched notes + DB questions for that chapter code.
     *
     * @return array<string, mixed>
     */
    public function getChapter(User $user, ExamSubject $subject, string $chapterCode): array
    {
        $code = strtoupper(trim($chapterCode));
        if ($code === '' || ! preg_match('/^[A-Z0-9_-]{1,40}$/', $code)) {
            throw new RuntimeException('Invalid chapter code.');
        }

        $chapter = ExamContentChapter::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->where('code', $code)
            ->first();

        $notes = ExamContentNote::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->orderBy('relative_path')
            ->get(['relative_path', 'title', 'body', 'updated_at']);

        $matchedNotes = [];
        $sharedExcerpts = [];
        foreach ($notes as $note) {
            $hay = strtoupper(($note->relative_path ?? '').' '.($note->title ?? ''));
            if (preg_match('/(?:^|[^A-Z0-9])'.preg_quote($code, '/').'(?:[^A-Z0-9]|$)/', $hay)) {
                $matchedNotes[] = [
                    'relative_path' => $note->relative_path,
                    'title' => $note->title,
                    'body' => $note->body,
                    'updated_at' => $note->updated_at?->toIso8601String(),
                ];
                continue;
            }

            // Pull a relevant slice from day micro-capsule / plan style notes
            $excerpt = $this->excerptForChapter($note->body ?? '', $code, $chapter?->title);
            if ($excerpt !== null) {
                $sharedExcerpts[] = [
                    'relative_path' => $note->relative_path,
                    'title' => $note->title,
                    'excerpt' => $excerpt,
                ];
            }
        }

        $questions = ExamContentQuestion::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->where('chapter_code', $code)
            ->orderBy('set_key')
            ->orderBy('question_no')
            ->limit(30)
            ->get([
                'external_id',
                'set_key',
                'question_no',
                'stem',
                'options',
                'correct_answer',
                'explanation',
            ])
            ->map(fn (ExamContentQuestion $q) => [
                'external_id' => $q->external_id,
                'set_key' => $q->set_key,
                'question_no' => $q->question_no,
                'stem' => $q->stem,
                'options' => $q->options,
                'correct_answer' => $q->correct_answer,
                'explanation' => $q->explanation,
            ])
            ->all();

        $questionCount = ExamContentQuestion::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->where('chapter_code', $code)
            ->count();

        return [
            'chapter' => [
                'code' => $code,
                'title' => $chapter?->title,
                'status' => $chapter?->status,
                'mcq_used' => $chapter?->mcq_used,
                'mcq_target' => $chapter?->mcq_target,
                'question_count' => $questionCount,
            ],
            'notes' => $matchedNotes,
            'shared_excerpts' => $sharedExcerpts,
            'questions' => $questions,
        ];
    }

    private function excerptForChapter(string $body, string $code, ?string $chapterTitle): ?string
    {
        $body = trim($body);
        if ($body === '') {
            return null;
        }

        // Direct heading mentioning the code
        if (preg_match('/^#{1,3}\s*[^\n]*'.preg_quote($code, '/').'[^\n]*$/mi', $body, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $slice = substr($body, $start);
            if (preg_match('/\n#{1,3}\s+/', $slice, $n, PREG_OFFSET_CAPTURE, 1)) {
                $slice = substr($slice, 0, $n[0][1]);
            }

            return trim($this->utf8(mb_substr($slice, 0, 2500)));
        }

        $group = match (true) {
            in_array($code, ['G01', 'G02', 'G03'], true) => ['বিজ্ঞান', 'Science'],
            in_array($code, ['G04', 'G05', 'G06'], true) => ['ইতিহাস', 'History'],
            in_array($code, ['G07', 'G08', 'G09'], true) => ['ভূগোল', 'Geography'],
            $code === 'G10' => ['রাজনীতি', 'Polity', 'সংবিধান'],
            $code === 'G11' => ['অর্থনীতি', 'Economy'],
            $code === 'G12' => ['Current affairs', 'সাধারণ'],
            default => [],
        };

        foreach ($group as $label) {
            if (preg_match('/^##\s*[^\n]*'.preg_quote($label, '/').'[^\n]*$/miu', $body, $m, PREG_OFFSET_CAPTURE)) {
                $start = $m[0][1];
                $slice = substr($body, $start);
                if (preg_match('/\n##\s+/u', $slice, $n, PREG_OFFSET_CAPTURE, 1)) {
                    $slice = substr($slice, 0, $n[0][1]);
                }

                return trim($this->utf8(mb_substr($slice, 0, 2500)));
            }
        }

        // Day-plan table row for this chapter
        if ($chapterTitle && preg_match('/\|[^\n]*'.preg_quote($code, '/').'[^\n]*\|/u', $body, $row)) {
            return trim($this->utf8($row[0]));
        }

        return null;
    }

    /**
     * @return array{chapters:int,questions:int,notes:int}
     */
    private function summaryCounts(User $user, ExamSubject $subject): array
    {
        return [
            'chapters' => ExamContentChapter::query()
                ->where('user_id', $user->id)
                ->where('exam_subject_id', $subject->id)
                ->count(),
            'questions' => ExamContentQuestion::query()
                ->where('user_id', $user->id)
                ->where('exam_subject_id', $subject->id)
                ->count(),
            'notes' => ExamContentNote::query()
                ->where('user_id', $user->id)
                ->where('exam_subject_id', $subject->id)
                ->count(),
        ];
    }

    private function noteTitle(string $body, string $fallback): string
    {
        if (preg_match('/^#\s+(.+)$/m', $body, $m)) {
            return trim($m[1]);
        }

        return $fallback;
    }

    private function readUtf8File(string $path): string
    {
        return $this->utf8((string) file_get_contents($path));
    }

    private function utf8(string $value): string
    {
        // Strip UTF-8 BOM
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        if ($value === '') {
            return '';
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $detected = mb_detect_encoding(
                $value,
                ['UTF-8', 'UTF-16LE', 'UTF-16BE', 'ISO-8859-1', 'Windows-1252'],
                true,
            );
            $converted = @mb_convert_encoding($value, 'UTF-8', $detected ?: 'ISO-8859-1');
            $value = is_string($converted) ? $converted : $value;
        }

        // Drop any remaining invalid byte sequences so json_encode never blows up
        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($clean) ? $clean : '';
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function utf8Deep(mixed $value): mixed
    {
        if (is_string($value)) {
            return $this->utf8($value);
        }
        if (! is_array($value)) {
            return $value;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $key = is_string($k) ? $this->utf8($k) : $k;
            $out[$key] = $this->utf8Deep($v);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function contentHash(array $payload): string
    {
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );

        return hash('sha256', is_string($json) ? $json : serialize($payload));
    }

    private function packDir(User $user, ExamSet $exam, ExamSubject $subject): string
    {
        if ($subject->exam_set_id !== $exam->id) {
            throw new RuntimeException('Subject does not belong to this exam.');
        }

        $state = ExamSubjectState::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->first();

        if (! $state?->storage_path) {
            throw new RuntimeException('No synced pack for this subject. Upload/sync the zip first, then import.');
        }

        $dir = storage_path('app/private/'.$state->storage_path);
        if (! is_dir($dir)) {
            throw new RuntimeException('Synced pack folder is missing. Re-upload the zip, then import.');
        }

        return $dir;
    }
}
