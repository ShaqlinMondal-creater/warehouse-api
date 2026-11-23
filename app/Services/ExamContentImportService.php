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
    public function __construct(private ExamMcqSetParser $parser) {}

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
        $setsDir = $dir.DIRECTORY_SEPARATOR.'practice'.DIRECTORY_SEPARATOR.'sets';
        if (! is_dir($setsDir)) {
            return;
        }

        foreach (File::files($setsDir) as $file) {
            $name = $file->getFilename();
            if (! preg_match('/^(DAY-\d+)-Q\.md$/i', $name, $m)) {
                continue;
            }

            $setKey = strtoupper($m[1]);
            $stats['sets_scanned']++;
            $qPath = $file->getPathname();
            $aName = $setKey.'-ANSWERS.md';
            $aPath = $setsDir.DIRECTORY_SEPARATOR.$aName;
            $qRel = 'practice/sets/'.$name;
            $aRel = is_file($aPath) ? 'practice/sets/'.$aName : null;

            $questions = $this->parser->parseQuestions($this->readUtf8File($qPath));
            $answers = is_file($aPath)
                ? $this->parser->parseAnswers($this->readUtf8File($aPath))
                : [];

            foreach ($questions as $q) {
                $externalId = $this->utf8(trim((string) $q['id']));
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
