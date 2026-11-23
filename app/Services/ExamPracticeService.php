<?php

namespace App\Services;

use App\Models\ExamPracticeAttempt;
use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Models\User;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ExamPracticeService
{
    public function __construct(private ExamMcqSetParser $parser) {}

    /**
     * @return list<array{key:string,label:string,questions_file:string,answers_file:string|null,question_count:int|null}>
     */
    public function listSets(User $user, ExamSet $exam, ExamSubject $subject): array
    {
        $dir = $this->packDir($user, $exam, $subject);
        $setsDir = $dir.DIRECTORY_SEPARATOR.'practice'.DIRECTORY_SEPARATOR.'sets';
        if (! is_dir($setsDir)) {
            return [];
        }

        $out = [];
        foreach (File::files($setsDir) as $file) {
            $name = $file->getFilename();
            if (! preg_match('/^(DAY-\d+)-Q\.md$/i', $name, $m)) {
                continue;
            }
            $key = strtoupper($m[1]);
            $answersName = $key.'-ANSWERS.md';
            $answersPath = $setsDir.DIRECTORY_SEPARATOR.$answersName;
            $count = null;
            try {
                $count = count($this->parser->parseQuestions((string) file_get_contents($file->getPathname())));
            } catch (\Throwable) {
                $count = null;
            }

            $out[] = [
                'key' => $key,
                'label' => $key,
                'questions_file' => 'practice/sets/'.$name,
                'answers_file' => is_file($answersPath) ? 'practice/sets/'.$answersName : null,
                'question_count' => $count,
            ];
        }

        usort($out, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSet(User $user, ExamSet $exam, ExamSubject $subject, string $setKey): array
    {
        [$qPath, $aPath, $key] = $this->resolveSetPaths($user, $exam, $subject, $setKey);
        $questions = $this->parser->parseQuestions((string) file_get_contents($qPath));
        if ($questions === []) {
            throw new RuntimeException('No questions parsed from this set.');
        }

        $progress = $this->readProgress($this->packDir($user, $exam, $subject));

        return [
            'set_key' => $key,
            'questions_file' => 'practice/sets/'.basename($qPath),
            'has_answers' => is_file($aPath),
            'total' => count($questions),
            'current_from_progress' => $progress['currentSet'] ?? null,
            'questions' => $questions,
        ];
    }

    /**
     * @param  array<string, string>  $answers  qNo => A|B|C|D
     * @return array<string, mixed>
     */
    public function submit(
        User $user,
        ExamSet $exam,
        ExamSubject $subject,
        string $setKey,
        array $answers,
        ?string $startedAt = null,
    ): array {
        [$qPath, $aPath, $key] = $this->resolveSetPaths($user, $exam, $subject, $setKey);
        if (! is_file($aPath)) {
            throw new RuntimeException('Answer key not found for this set.');
        }

        $questions = $this->parser->parseQuestions((string) file_get_contents($qPath));
        $answerMap = $this->parser->parseAnswers((string) file_get_contents($aPath));

        $normalized = [];
        foreach ($answers as $no => $opt) {
            $n = (int) $no;
            $letter = strtoupper(trim((string) $opt));
            if ($n > 0 && in_array($letter, ['A', 'B', 'C', 'D'], true)) {
                $normalized[$n] = $letter;
            }
        }

        $results = [];
        $correct = 0;
        $incorrect = 0;
        $answered = 0;

        foreach ($questions as $q) {
            $no = $q['no'];
            $selected = $normalized[$no] ?? null;
            $keyRow = $answerMap[$no] ?? null;
            $expected = $keyRow['answer'] ?? null;
            $isCorrect = $selected !== null && $expected !== null && $selected === $expected;
            if ($selected !== null) {
                $answered++;
                if ($isCorrect) {
                    $correct++;
                } else {
                    $incorrect++;
                }
            }

            $results[] = [
                'no' => $no,
                'id' => $q['id'],
                'chapter' => $q['chapter'],
                'selected' => $selected,
                'correct_answer' => $expected,
                'is_correct' => $selected === null ? null : $isCorrect,
                'explanation' => $keyRow['explanation'] ?? null,
                'stem' => $q['stem'],
                'options' => $q['options'],
            ];
        }

        $total = count($questions);
        $accuracy = $answered > 0 ? round(($correct / $answered) * 100, 2) : null;

        $attempt = ExamPracticeAttempt::query()->create([
            'user_id' => $user->id,
            'exam_subject_id' => $subject->id,
            'set_key' => $key,
            'questions_file' => 'practice/sets/'.basename($qPath),
            'total' => $total,
            'answered' => $answered,
            'correct' => $correct,
            'incorrect' => $incorrect,
            'accuracy_percent' => $accuracy,
            'answers' => $normalized,
            'results' => array_map(static fn ($r) => [
                'no' => $r['no'],
                'id' => $r['id'],
                'chapter' => $r['chapter'],
                'selected' => $r['selected'],
                'correct_answer' => $r['correct_answer'],
                'is_correct' => $r['is_correct'],
            ], $results),
            'started_at' => $startedAt ? date('Y-m-d H:i:s', strtotime($startedAt) ?: time()) : now(),
            'submitted_at' => now(),
        ]);

        return [
            'attempt' => $attempt,
            'summary' => [
                'set_key' => $key,
                'total' => $total,
                'answered' => $answered,
                'correct' => $correct,
                'incorrect' => $incorrect,
                'unanswered' => max(0, $total - $answered),
                'accuracy_percent' => $accuracy,
            ],
            'results' => $results,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentAttempts(User $user, ExamSubject $subject, int $limit = 10): array
    {
        return ExamPracticeAttempt::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Per-user performance from attempts only (no pack content).
     *
     * @return array{attempts:list<array<string,mixed>>,by_date:list<array<string,mixed>>,by_set:list<array<string,mixed>>,by_chapter:list<array<string,mixed>>,totals:array<string,mixed>}
     */
    public function performance(User $user, ExamSubject $subject, int $limit = 50): array
    {
        $attempts = ExamPracticeAttempt::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $byDate = [];
        $bySet = [];
        $byChapter = [];

        $totalAttempts = 0;
        $sumCorrect = 0;
        $sumAnswered = 0;

        foreach ($attempts as $attempt) {
            $totalAttempts++;
            $correct = (int) $attempt->correct;
            $answered = (int) $attempt->answered;
            $sumCorrect += $correct;
            $sumAnswered += $answered;

            $day = $attempt->submitted_at
                ? $attempt->submitted_at->timezone(config('app.timezone'))->format('Y-m-d')
                : ($attempt->created_at?->timezone(config('app.timezone'))->format('Y-m-d') ?? 'unknown');

            if (! isset($byDate[$day])) {
                $byDate[$day] = [
                    'date' => $day,
                    'attempts' => 0,
                    'correct' => 0,
                    'answered' => 0,
                    'accuracy_percent' => null,
                ];
            }
            $byDate[$day]['attempts']++;
            $byDate[$day]['correct'] += $correct;
            $byDate[$day]['answered'] += $answered;

            $setKey = (string) $attempt->set_key;
            if (! isset($bySet[$setKey])) {
                $bySet[$setKey] = [
                    'set_key' => $setKey,
                    'attempts' => 0,
                    'correct' => 0,
                    'answered' => 0,
                    'best_accuracy_percent' => null,
                    'last_submitted_at' => null,
                ];
            }
            $bySet[$setKey]['attempts']++;
            $bySet[$setKey]['correct'] += $correct;
            $bySet[$setKey]['answered'] += $answered;
            $acc = $attempt->accuracy_percent !== null ? (float) $attempt->accuracy_percent : null;
            if ($acc !== null) {
                $best = $bySet[$setKey]['best_accuracy_percent'];
                if ($best === null || $acc > $best) {
                    $bySet[$setKey]['best_accuracy_percent'] = $acc;
                }
            }
            $submitted = $attempt->submitted_at?->toIso8601String();
            if ($submitted && (
                $bySet[$setKey]['last_submitted_at'] === null
                || $submitted > $bySet[$setKey]['last_submitted_at']
            )) {
                $bySet[$setKey]['last_submitted_at'] = $submitted;
            }

            $rows = is_array($attempt->results) ? $attempt->results : [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $chapter = trim((string) ($row['chapter'] ?? ''));
                if ($chapter === '') {
                    $chapter = 'Unknown';
                }
                if (! isset($byChapter[$chapter])) {
                    $byChapter[$chapter] = [
                        'chapter' => $chapter,
                        'seen' => 0,
                        'correct' => 0,
                        'incorrect' => 0,
                        'skipped' => 0,
                        'accuracy_percent' => null,
                    ];
                }
                $isCorrect = $row['is_correct'] ?? null;
                // Only graded answers count as "seen" for knowledge tracking
                if ($isCorrect === true) {
                    $byChapter[$chapter]['seen']++;
                    $byChapter[$chapter]['correct']++;
                } elseif ($isCorrect === false) {
                    $byChapter[$chapter]['seen']++;
                    $byChapter[$chapter]['incorrect']++;
                } else {
                    $byChapter[$chapter]['skipped']++;
                }
            }
        }

        foreach ($byDate as &$d) {
            $d['accuracy_percent'] = $d['answered'] > 0
                ? round(($d['correct'] / $d['answered']) * 100, 2)
                : null;
        }
        unset($d);

        foreach ($bySet as &$s) {
            $s['accuracy_percent'] = $s['answered'] > 0
                ? round(($s['correct'] / $s['answered']) * 100, 2)
                : null;
        }
        unset($s);

        foreach ($byChapter as &$c) {
            $graded = $c['correct'] + $c['incorrect'];
            $c['accuracy_percent'] = $graded > 0
                ? round(($c['correct'] / $graded) * 100, 2)
                : null;
        }
        unset($c);

        $dateList = array_values($byDate);
        usort($dateList, fn ($a, $b) => strcmp($b['date'], $a['date']));

        $setList = array_values($bySet);
        usort($setList, fn ($a, $b) => strcmp($a['set_key'], $b['set_key']));

        $chapterList = array_values($byChapter);
        usort($chapterList, function ($a, $b) {
            $aa = $a['accuracy_percent'];
            $bb = $b['accuracy_percent'];
            if ($aa === null && $bb === null) {
                return strcmp($a['chapter'], $b['chapter']);
            }
            if ($aa === null) {
                return 1;
            }
            if ($bb === null) {
                return -1;
            }

            return $aa <=> $bb;
        });

        $attemptRows = $attempts->map(static function (ExamPracticeAttempt $a) {
            return [
                'id' => $a->id,
                'set_key' => $a->set_key,
                'total' => $a->total,
                'answered' => $a->answered,
                'correct' => $a->correct,
                'incorrect' => $a->incorrect,
                'accuracy_percent' => $a->accuracy_percent,
                'submitted_at' => $a->submitted_at?->toIso8601String(),
                'started_at' => $a->started_at?->toIso8601String(),
            ];
        })->all();

        return [
            'attempts' => $attemptRows,
            'by_date' => $dateList,
            'by_set' => $setList,
            'by_chapter' => $chapterList,
            'totals' => [
                'attempts' => $totalAttempts,
                'correct' => $sumCorrect,
                'answered' => $sumAnswered,
                'accuracy_percent' => $sumAnswered > 0
                    ? round(($sumCorrect / $sumAnswered) * 100, 2)
                    : null,
            ],
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function resolveSetPaths(User $user, ExamSet $exam, ExamSubject $subject, string $setKey): array
    {
        $key = strtoupper(trim($setKey));
        if (! preg_match('/^DAY-\d+$/', $key)) {
            throw new RuntimeException('Invalid practice set key.');
        }

        $dir = $this->packDir($user, $exam, $subject);
        $setsDir = $dir.DIRECTORY_SEPARATOR.'practice'.DIRECTORY_SEPARATOR.'sets';
        $qPath = $setsDir.DIRECTORY_SEPARATOR.$key.'-Q.md';
        $aPath = $setsDir.DIRECTORY_SEPARATOR.$key.'-ANSWERS.md';

        if (! is_file($qPath)) {
            throw new RuntimeException("Questions file not found for {$key}.");
        }

        return [$qPath, $aPath, $key];
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
            throw new RuntimeException('No synced pack for this subject. Upload a zip first.');
        }

        $dir = storage_path('app/private/'.$state->storage_path);
        if (! is_dir($dir)) {
            throw new RuntimeException('Synced pack folder is missing. Re-upload the zip.');
        }

        return $dir;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readProgress(string $dir): ?array
    {
        $path = $dir.DIRECTORY_SEPARATOR.'progress.json';
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
