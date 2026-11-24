<?php

namespace App\Support;

use App\Models\Bid;
use Illuminate\Support\Collection;

/**
 * Project-name matching shared by extract (review preview) and import (save).
 *
 * Screenshots truncate long titles with "..." and OCR can drop case or punctuation,
 * so names are compared by a normalized key instead of raw string equality.
 * The frontend duplicate filter uses the same rules (see duplicates.ts).
 */
final class BidName
{
    /** Prefix matches need this many key characters, so short truncated names never merge by accident. */
    private const MIN_PREFIX_LENGTH = 12;

    /** Normalized comparison key: lowercase, no trailing ellipsis, no punctuation, single spaces. */
    public static function key(string $name): string
    {
        $text = mb_strtolower(trim($name));
        $text = preg_replace('/(\.{3}|…)\s*$/u', '', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /** True when the raw name ends with a screenshot truncation marker. */
    public static function isTruncated(string $name): bool
    {
        return preg_match('/(\.{3}|…)\s*$/u', trim($name)) === 1;
    }

    /**
     * Find the saved bid that an incoming name refers to.
     *
     * 1. Same normalized key (case, punctuation and "..." ignored).
     * 2. Incoming name is truncated: a single saved bid whose key starts with it.
     * 3. Saved name is truncated: a single saved bid whose truncated key is a prefix of the incoming key.
     *
     * Prefix matches only apply when exactly one bid fits, so ambiguous names stay separate.
     *
     * @param  Collection<int, Bid>  $candidates
     */
    public static function match(string $incoming, Collection $candidates): ?Bid
    {
        $key = self::key($incoming);
        if ($key === '') {
            return null;
        }

        $exact = $candidates->first(fn (Bid $bid) => self::key((string) $bid->project_name) === $key);
        if ($exact) {
            return $exact;
        }

        if (mb_strlen($key) < self::MIN_PREFIX_LENGTH) {
            return null;
        }

        if (self::isTruncated($incoming)) {
            $matches = $candidates->filter(
                fn (Bid $bid) => str_starts_with(self::key((string) $bid->project_name), $key)
            );

            return $matches->count() === 1 ? $matches->first() : null;
        }

        $matches = $candidates->filter(function (Bid $bid) use ($key) {
            if (! self::isTruncated((string) $bid->project_name)) {
                return false;
            }
            $savedKey = self::key((string) $bid->project_name);

            return mb_strlen($savedKey) >= self::MIN_PREFIX_LENGTH && str_starts_with($key, $savedKey);
        });

        return $matches->count() === 1 ? $matches->first() : null;
    }
}
