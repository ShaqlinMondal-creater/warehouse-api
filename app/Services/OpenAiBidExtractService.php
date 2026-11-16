<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiBidExtractService
{
    /**
     * @return list<array{
     *   project_name: string,
     *   total_bids: int|null,
     *   average_bid_raw: string|null,
     *   average_bid: float|null,
     *   my_bid_raw: string|null,
     *   my_bid: float|null,
     *   bid_type: string|null,
     *   bid_placed_raw: string|null,
     *   bid_placed_at: string|null
     * }>
     */
    public function extractFromImage(UploadedFile $file): array
    {
        $apiKey = config('services.openai.api_key');
        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException(
                'OPENAI_API_KEY is not set on the API server .env (api.warehouse.liwaas.com). Add it there, set WAREHOUSE_AI_ENABLED=true, then run: php artisan config:clear'
            );
        }

        if (! config('warehouse.ai.enabled', false)) {
            throw new RuntimeException('AI extract is disabled. Set WAREHOUSE_AI_ENABLED=true in .env.');
        }

        $model = (string) config('warehouse.ai.model', 'gpt-4o-mini');
        $mime = $file->getMimeType() ?: 'image/png';
        $base64 = base64_encode((string) file_get_contents($file->getRealPath()));
        $dataUrl = 'data:'.$mime.';base64,'.$base64;

        $prompt = <<<'PROMPT'
You read a Freelancer (or similar) "Bids" table screenshot.
Return ONLY valid JSON (no markdown) as an array of objects with keys:
- project_name (string)
- total_bids (integer or null)
- average_bid_raw (string exactly as shown, or null)
- my_bid_raw (string exactly as shown, or null)
- bid_placed_raw (string exactly as shown, e.g. "about 5 hours ago", or null)

Rules:
- One object per project row.
- Keep currency text in *_raw fields.
- If a cell is missing, use null.
PROMPT;

        $response = Http::withToken($apiKey)
            ->timeout(90)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You extract bid table rows from screenshots. Reply with JSON only.',
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt.' Wrap the array in {"bids":[...]}.'],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
                'max_tokens' => (int) config('warehouse.ai.max_tokens', 2000),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenAI request failed: '.$response->body());
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned empty content.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        $rows = $decoded['bids'] ?? $decoded;
        if (! is_array($rows)) {
            throw new RuntimeException('OpenAI JSON missing bids array.');
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $project = trim((string) ($row['project_name'] ?? ''));
            if ($project === '') {
                continue;
            }

            $avgRaw = $this->nullableString($row['average_bid_raw'] ?? null);
            $myRaw = $this->nullableString($row['my_bid_raw'] ?? null);
            $placedRaw = $this->nullableString($row['bid_placed_raw'] ?? null);

            $normalized[] = [
                'project_name' => $project,
                'total_bids' => $this->nullableInt($row['total_bids'] ?? null),
                'average_bid_raw' => $avgRaw,
                'average_bid' => $this->parseMoney($avgRaw),
                'my_bid_raw' => $myRaw,
                'my_bid' => $this->parseMoney($myRaw),
                'bid_type' => $this->detectBidType($myRaw) ?? $this->detectBidType($avgRaw),
                'bid_placed_raw' => $placedRaw,
                'bid_placed_at' => $this->parseRelativeTime($placedRaw),
            ];
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        if (is_string($value) && preg_match('/\d+/', $value, $m)) {
            return (int) $m[0];
        }

        return null;
    }

    private function parseMoney(?string $raw): ?float
    {
        if ($raw === null) {
            return null;
        }
        $clean = preg_replace('/[^\d.]/', '', str_replace(',', '', $raw));
        if ($clean === null || $clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return round((float) $clean, 2);
    }

    private function detectBidType(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return str_contains(strtolower($raw), 'hour') ? 'hourly' : 'fixed';
    }

    private function parseRelativeTime(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = strtolower(trim($raw));
        $text = preg_replace('/^about\s+/', '', $text) ?? $text;
        $now = now();

        if (preg_match('/(\d+)\s*minute/', $text, $m)) {
            return $now->copy()->subMinutes((int) $m[1])->toIso8601String();
        }
        if (preg_match('/(\d+)\s*hour/', $text, $m)) {
            return $now->copy()->subHours((int) $m[1])->toIso8601String();
        }
        if (preg_match('/(\d+)\s*day/', $text, $m)) {
            return $now->copy()->subDays((int) $m[1])->toIso8601String();
        }
        if (str_contains($text, 'yesterday')) {
            return $now->copy()->subDay()->toIso8601String();
        }

        return null;
    }
}
