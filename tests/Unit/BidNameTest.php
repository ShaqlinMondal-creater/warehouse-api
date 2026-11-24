<?php

namespace Tests\Unit;

use App\Models\Bid;
use App\Support\BidName;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class BidNameTest extends TestCase
{
    public function test_key_ignores_case_punctuation_spacing_and_trailing_ellipsis(): void
    {
        $this->assertSame('custom joydip website', BidName::key('Custom Joydip Website...'));
        $this->assertSame('custom joydip website', BidName::key('  custom   Joydip-Website  '));
        $this->assertSame('custom joydip website', BidName::key('Custom Joydip Website…'));
    }

    public function test_same_key_matches_existing_bid(): void
    {
        $saved = new Bid(['project_name' => 'Custom Joydip Website']);
        $candidates = collect([$saved]);

        $this->assertSame($saved, BidName::match('Custom Joydip Website...', $candidates));
        $this->assertSame($saved, BidName::match('custom joydip website', $candidates));
    }

    public function test_truncated_incoming_name_matches_single_full_name(): void
    {
        $saved = new Bid(['project_name' => 'Website Layout Improvement for Clinic']);
        $candidates = collect([$saved, new Bid(['project_name' => 'Build a Custom School'])]);

        $this->assertSame($saved, BidName::match('Website Layout Improvement...', $candidates));
    }

    public function test_truncated_incoming_name_with_several_matches_stays_separate(): void
    {
        $candidates = collect([
            new Bid(['project_name' => 'Website Layout Improvement for Clinic']),
            new Bid(['project_name' => 'Website Layout Improvement for Store']),
        ]);

        $this->assertNull(BidName::match('Website Layout Improvement...', $candidates));
    }

    public function test_truncated_saved_name_matches_full_incoming_name(): void
    {
        $saved = new Bid(['project_name' => 'Modern Responsive E...']);
        $candidates = collect([$saved]);

        $this->assertSame($saved, BidName::match('Modern Responsive E-commerce Store', $candidates));
    }

    public function test_short_truncated_names_do_not_match_by_prefix(): void
    {
        $candidates = collect([new Bid(['project_name' => 'Build a Custom School'])]);

        $this->assertNull(BidName::match('Build...', $candidates));
    }

    public function test_different_projects_do_not_match_exactly(): void
    {
        $candidates = collect([new Bid(['project_name' => 'Custom Jopid Website'])]);

        // OCR-style typos are left to the frontend duplicate filter, never merged on import.
        $this->assertNull(BidName::match('Custom Joydip Website', $candidates));
    }

    public function test_empty_name_never_matches(): void
    {
        $candidates = new Collection([new Bid(['project_name' => ''])]);

        $this->assertNull(BidName::match('   ', $candidates));
    }
}
