<?php

namespace App\Services;

use App\Models\ExamApplication;
use App\Models\ExamPracticeAttempt;
use App\Models\User;

/**
 * Performance analytics for one guest, built only from their own practice attempts.
 */
class GuestAnalyticsService
{
    private const MAX_ATTEMPTS = 300;

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $attempts = ExamPracticeAttempt::query()
            ->with('subject.examSet')
            ->where('user_id', $user->id)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ATTEMPTS)
            ->get();

        $tz = config('app.timezone');

        $answered = 0;
        $correct = 0;
        $best = null;
        $days = [];
        $byDate = [];
        $bySubject = [];
        $byChapter = [];

        foreach ($attempts as $a) {
            $answered += (int) $a->answered;
            $correct += (int) $a->correct;
            if ($a->accuracy_percent !== null && ($best === null || $a->accuracy_percent > $best)) {
                $best = (float) $a->accuracy_percent;
            }

            $when = $a->submitted_at ?? $a->created_at;
            $day = $when?->timezone($tz)->format('Y-m-d') ?? 'unknown';
            $days[$day] = true;

            $byDate[$day] ??= ['date' => $day, 'attempts' => 0, 'answered' => 0, 'correct' => 0];
            $byDate[$day]['attempts']++;
            $byDate[$day]['answered'] += (int) $a->answered;
            $byDate[$day]['correct'] += (int) $a->correct;

            $subject = $a->subject;
            $exam = $subject?->examSet;
            $sKey = (string) $a->exam_subject_id;
            $bySubject[$sKey] ??= [
                'exam_slug' => $exam?->slug,
                'exam_title' => $exam?->title ?? 'Exam',
                'subject_slug' => $subject?->slug,
                'subject_title' => $subject?->title ?? 'Subject',
                'attempts' => 0,
                'answered' => 0,
                'correct' => 0,
                'last_submitted_at' => null,
            ];
            $bySubject[$sKey]['attempts']++;
            $bySubject[$sKey]['answered'] += (int) $a->answered;
            $bySubject[$sKey]['correct'] += (int) $a->correct;
            $iso = $a->submitted_at?->toIso8601String();
            if ($iso && ($bySubject[$sKey]['last_submitted_at'] === null || $iso > $bySubject[$sKey]['last_submitted_at'])) {
                $bySubject[$sKey]['last_submitted_at'] = $iso;
            }

            foreach (is_array($a->results) ? $a->results : [] as $row) {
                if (! is_array($row) || ! array_key_exists('is_correct', $row) || $row['is_correct'] === null) {
                    continue;
                }
                $chapter = trim((string) ($row['chapter'] ?? '')) ?: 'Unknown';
                $cKey = $sKey.'|'.$chapter;
                $byChapter[$cKey] ??= [
                    'chapter' => $chapter,
                    'subject_title' => $subject?->title ?? 'Subject',
                    'seen' => 0,
                    'correct' => 0,
                ];
                $byChapter[$cKey]['seen']++;
                if ($row['is_correct'] === true) {
                    $byChapter[$cKey]['correct']++;
                }
            }
        }

        $dateList = array_values($byDate);
        usort($dateList, fn ($x, $y) => strcmp($x['date'], $y['date']));
        $dateList = array_slice($dateList, -30);
        foreach ($dateList as &$d) {
            $d['accuracy_percent'] = $this->pct($d['correct'], $d['answered']);
        }
        unset($d);

        $subjectList = array_values($bySubject);
        foreach ($subjectList as &$s) {
            $s['accuracy_percent'] = $this->pct($s['correct'], $s['answered']);
        }
        unset($s);
        usort($subjectList, fn ($x, $y) => ($y['attempts'] <=> $x['attempts']));

        $chapterList = array_values($byChapter);
        foreach ($chapterList as &$c) {
            $c['accuracy_percent'] = $this->pct($c['correct'], $c['seen']);
        }
        unset($c);
        usort($chapterList, fn ($x, $y) => ($x['accuracy_percent'] <=> $y['accuracy_percent']) ?: ($y['seen'] <=> $x['seen']));

        $recent = $attempts->take(8)->map(fn (ExamPracticeAttempt $a) => [
            'id' => $a->id,
            'exam_title' => $a->subject?->examSet?->title,
            'subject_title' => $a->subject?->title,
            'set_key' => $a->set_key,
            'total' => $a->total,
            'answered' => $a->answered,
            'correct' => $a->correct,
            'accuracy_percent' => $a->accuracy_percent,
            'submitted_at' => $a->submitted_at?->toIso8601String(),
        ])->values()->all();

        // Trend: average accuracy of the latest 5 attempts vs. the 5 before them.
        $scored = $attempts->filter(fn ($a) => $a->accuracy_percent !== null)->values();
        $trend = null;
        if ($scored->count() >= 2) {
            $latest = $scored->take(5);
            $before = $scored->slice(5, 5);
            if ($before->count() > 0) {
                $trend = round($latest->avg('accuracy_percent') - $before->avg('accuracy_percent'), 1);
            }
        }

        $apps = ExamApplication::query()
            ->where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        return [
            'totals' => [
                'attempts' => $attempts->count(),
                'answered' => $answered,
                'correct' => $correct,
                'incorrect' => max(0, $answered - $correct),
                'accuracy_percent' => $this->pct($correct, $answered),
                'best_accuracy_percent' => $best,
                'active_days' => count($days),
                'trend_delta' => $trend,
                'last_activity_at' => $attempts->first()?->submitted_at?->toIso8601String(),
            ],
            'by_date' => $dateList,
            'by_subject' => $subjectList,
            'weakest_chapters' => array_slice(array_values(array_filter($chapterList, fn ($c) => $c['seen'] >= 3)), 0, 6),
            'strongest_chapters' => array_slice(array_reverse(array_values(array_filter($chapterList, fn ($c) => $c['seen'] >= 3))), 0, 6),
            'recent_attempts' => $recent,
            'applications' => [
                'pending' => (int) ($apps[ExamApplication::PENDING] ?? 0),
                'approved' => (int) ($apps[ExamApplication::APPROVED] ?? 0),
                'rejected' => (int) ($apps[ExamApplication::REJECTED] ?? 0),
            ],
        ];
    }

    private function pct(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 2) : null;
    }
}
