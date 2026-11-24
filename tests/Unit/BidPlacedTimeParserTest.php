<?php

namespace Tests\Unit;

use App\Support\BidPlacedTimeParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BidPlacedTimeParserTest extends TestCase
{
    private const TZ = 'Asia/Kolkata';

    private function now(): Carbon
    {
        // 2026-11-24 12:19 UTC
        return Carbon::parse('2026-11-24 12:19:00', 'UTC');
    }

    private function parse(?string $raw): ?string
    {
        return BidPlacedTimeParser::parse($raw, $this->now(), self::TZ);
    }

    public function test_minutes_ago_is_exact_offset_from_now(): void
    {
        $result = $this->parse('5 minutes ago');

        $this->assertNotNull($result);
        $this->assertTrue(Carbon::parse($result)->equalTo($this->now()->copy()->subMinutes(5)));
    }

    public function test_hours_and_minutes_are_combined(): void
    {
        $result = $this->parse('1 hour 30 minutes ago');

        $this->assertTrue(Carbon::parse($result)->equalTo($this->now()->copy()->subMinutes(90)));
    }

    public function test_days_ago_becomes_noon_on_that_day_in_screenshot_timezone(): void
    {
        $result = $this->parse('2 days ago');

        // 2026-11-24 is 17:49 IST, so two days back is 2026-11-22.
        $this->assertSame('2026-11-22 12:00', Carbon::parse($result)->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_yesterday_and_article_forms_are_day_level(): void
    {
        $this->assertSame('2026-11-23 12:00', Carbon::parse($this->parse('yesterday'))->setTimezone(self::TZ)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-23 12:00', Carbon::parse($this->parse('a day ago'))->setTimezone(self::TZ)->format('Y-m-d H:i'));
        $this->assertSame('2026-11-17 12:00', Carbon::parse($this->parse('1 week ago'))->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_just_now_is_now(): void
    {
        $this->assertTrue(Carbon::parse($this->parse('just now'))->equalTo($this->now()));
    }

    public function test_countdowns_are_not_placement_times(): void
    {
        $this->assertNull($this->parse('2 days left'));
        $this->assertNull($this->parse('3 hours remaining'));
        $this->assertNull($this->parse('in 3 days'));
    }

    public function test_absolute_date_is_read_in_screenshot_timezone(): void
    {
        $result = $this->parse('Nov 23, 2026 06:06 PM');

        $this->assertSame('2026-11-23 18:06', Carbon::parse($result)->setTimezone(self::TZ)->format('Y-m-d H:i'));
    }

    public function test_empty_or_unknown_text_is_null(): void
    {
        $this->assertNull($this->parse(null));
        $this->assertNull($this->parse('   '));
        $this->assertNull($this->parse('Bid placed'));
    }
}
