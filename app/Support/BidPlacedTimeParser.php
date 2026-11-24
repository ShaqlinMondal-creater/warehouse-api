<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Turns the "Bid Placed" text from a screenshot into a stored timestamp.
 *
 * - Minutes/hours ago: exact offset from the moment the screenshot was read.
 * - Days/weeks/months/years ago, yesterday: only the day is known, so the time is set to
 *   noon in the screenshot timezone instead of a fake clock time.
 * - Absolute dates ("Nov 23, 06:06 PM"): read in the screenshot timezone.
 * - Countdowns ("2 days left", "in 3 days") are deadlines, not placement times: null.
 */
final class BidPlacedTimeParser
{
    private const COUNTDOWN = '/\b(left|remaining|to go|until|deadline|closes?|ends?)\b|^in\s+\d/u';

    private const RELATIVE = '/\b(\d+|an?|one)\s*(minute|min|hour|hr|day|week|month|year)s?\b/u';

    private const MONTH_NAME = '/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\b/u';

    public static function parse(?string $raw, CarbonInterface $now, string $timezone): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $text = mb_strtolower(trim($raw));
        $text = preg_replace('/^(about|approximately|around|~)\s*/u', '', $text) ?? $text;

        if (preg_match(self::COUNTDOWN, $text) === 1) {
            return null;
        }

        if (preg_match('/\b(just now|moments? ago|seconds? ago)\b/u', $text) === 1) {
            return $now->toIso8601String();
        }

        if (str_contains($text, 'yesterday')) {
            return self::dayLevel($now, $timezone, 0, 1);
        }

        if (preg_match_all(self::RELATIVE, $text, $matches, PREG_SET_ORDER) > 0) {
            $minutes = 0;
            $days = 0;
            $months = 0;

            foreach ($matches as $match) {
                $count = ctype_digit($match[1]) ? (int) $match[1] : 1;

                match ($match[2]) {
                    'minute', 'min' => $minutes += $count,
                    'hour', 'hr' => $minutes += $count * 60,
                    'day' => $days += $count,
                    'week' => $days += $count * 7,
                    'month' => $months += $count,
                    'year' => $months += $count * 12,
                };
            }

            if ($days > 0 || $months > 0) {
                return self::dayLevel($now, $timezone, $months, $days);
            }

            return $now->copy()->subMinutes($minutes)->toIso8601String();
        }

        return self::absolute($raw, $text, $now, $timezone);
    }

    private static function dayLevel(CarbonInterface $now, string $timezone, int $months, int $days): string
    {
        return $now->copy()
            ->timezone($timezone)
            ->subMonths($months)
            ->subDays($days)
            ->setTime(12, 0)
            ->toIso8601String();
    }

    private static function absolute(string $raw, string $text, CarbonInterface $now, string $timezone): ?string
    {
        if (preg_match(self::MONTH_NAME, $text) !== 1 || preg_match('/\d/', $text) !== 1) {
            return null;
        }

        try {
            $parsed = Carbon::parse($raw, $timezone);
        } catch (Throwable) {
            return null;
        }

        if (! str_contains($text, ':')) {
            $parsed = $parsed->setTime(12, 0);
        }

        // Screenshots often omit the year. A date that lands in the future means last year.
        if (preg_match('/\b\d{4}\b/', $text) !== 1 && $parsed->greaterThan($now->copy()->addDay())) {
            $parsed = $parsed->subYear();
        }

        return $parsed->toIso8601String();
    }
}
