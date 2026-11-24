<?php

namespace App\Services;

use App\Models\AiModel;
use App\Support\BidPlacedTimeParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BidExtractService
{
    /**
     * @return list<array{
     *   project_name: string,
     *   total_bids: int|null,
     *   my_rank: string|null,
     *   client_viewed: bool|null,
     *   average_bid_raw: string|null,
     *   average_bid: float|null,
     *   my_bid_raw: string|null,
     *   my_bid: float|null,
     *   bid_type: string|null,
     *   bid_placed_raw: string|null,
     *   bid_placed_at: string|null
     * }>
     */
    public function extractFromImage(UploadedFile $file, ?AiModel $aiModel = null): array
    {
        if (! config('warehouse.ai.enabled', false)) {
            throw new RuntimeException('AI extract is disabled. Set WAREHOUSE_AI_ENABLED=true in .env.');
        }

        $provider = $aiModel?->provider ?? (string) config('warehouse.ai.provider', 'openai');
        $modelId = $aiModel?->model_id ?? (string) config('warehouse.ai.model', 'gpt-4o-mini');

        if ($aiModel) {
            if (! $aiModel->is_enabled) {
                throw new RuntimeException('Selected AI model is disabled.');
            }
            if ($aiModel->isExpired()) {
                throw new RuntimeException('Selected AI model expired on '.$aiModel->expires_at?->toDateString().'. Update the expire date in Configs → AI models.');
            }
        }

        $mime = $file->getMimeType() ?: 'image/png';
        $base64 = base64_encode((string) file_get_contents($file->getRealPath()));

        $apiKey = $aiModel?->resolvedApiToken();

        $content = match ($provider) {
            'openai' => $this->callOpenAi($modelId, $mime, $base64, $apiKey),
            'claude' => $this->callClaude($modelId, $mime, $base64, $apiKey),
            'openrouter' => $this->callOpenRouter($modelId, $mime, $base64, $apiKey),
            default => throw new RuntimeException('Unsupported AI provider: '.$provider),
        };

        return $this->normalizeRows($content);
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
You read a Freelancer (or similar) bids screenshot table.
Return ONLY valid JSON (no markdown, no thinking) as: {"bids":[...]}

Each object keys:
- project_name (string) — Project column (full visible title)
- total_bids (integer or null) — from "Total Bids" OR from Bid Rank "of N bids"
- my_rank (string or null) — from Bid Rank like "#96 of 111 bids" → "96"; "#100+" → "100+" (keep the plus); else null
- client_viewed — always false (do not try to detect viewed from icons)
- average_bid_raw (string or null) — Average Bid cell if present
- my_bid_raw (string or null) — "My Bid" OR "Your Bid" cell exactly as shown (e.g. "₹20,000.00 INR")
- bid_placed_raw (string or null) — the "Bid Placed" column text only, exactly as shown (e.g. "2 days ago", "Nov 23, 06:06 PM"). Never use "Time to bid" or countdown text like "2 days left"; use null for those
- bid_rank_raw (string or null) — full Bid Rank text e.g. "#96 of 111 bids"
- bid_type (string or null) — "hourly" if my/your bid contains "/ hour", else "fixed"

Rules:
- One object per visible project row.
- Keep currency symbols/text in *_raw fields.
- Missing cells → null.
- Support both table styles: (1) Project / Total Bids / Average Bid / My Bid / Bid Placed (2) Project / Time to bid / Bid Rank / Your Bid / Actions Taken (here bid_placed_raw is null because "Time to bid" is not a placed time).
PROMPT;
    }

    private function callOpenAi(string $model, string $mime, string $base64, ?string $apiKey = null): string
    {
        $apiKey = is_string($apiKey) && trim($apiKey) !== ''
            ? trim($apiKey)
            : config('services.openai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException(
                'No OpenAI token. Paste the API token in Configs → AI models (or set OPENAI_API_KEY in .env).'
            );
        }

        $dataUrl = 'data:'.$mime.';base64,'.$base64;

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
                            ['type' => 'text', 'text' => $this->prompt()],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ],
                ],
                'max_tokens' => (int) config('warehouse.ai.max_tokens', 2000),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->friendlyProviderError('OpenAI', $response->json(), $response->body()));
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('OpenAI returned empty content.');
        }

        return $content;
    }

    private function callOpenRouter(string $model, string $mime, string $base64, ?string $apiKey = null): string
    {
        $apiKey = is_string($apiKey) && trim($apiKey) !== ''
            ? trim($apiKey)
            : config('services.openrouter.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException(
                'No OpenRouter token. Paste sk-or-v1-... in Configs → AI models (or set OPENROUTER_API_KEY in .env).'
            );
        }

        $baseUrl = rtrim((string) config('services.openrouter.base_url', 'https://openrouter.ai/api/v1'), '/');
        $dataUrl = 'data:'.$mime.';base64,'.$base64;
        $maxTokens = max((int) config('warehouse.ai.max_tokens', 2000), 4096);

        $payload = [
            'model' => $model,
            'temperature' => 0,
            // Nemotron free often spends all tokens on reasoning → empty content.
            'reasoning' => [
                'enabled' => false,
                'exclude' => true,
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You extract Freelancer bid table rows from screenshots. Reply with JSON only. No markdown. No thinking.',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ['type' => 'text', 'text' => $this->prompt()],
                    ],
                ],
            ],
            'max_tokens' => $maxTokens,
        ];

        $content = null;
        $lastJson = null;
        $attempts = 0;

        while ($attempts < 2) {
            $attempts++;
            $response = Http::withToken($apiKey)
                ->withHeaders([
                    'HTTP-Referer' => (string) config('services.openrouter.http_referer', 'https://warehouse.liwaas.com'),
                    'X-Title' => (string) config('services.openrouter.app_title', 'Shaqlin Warehouse'),
                ])
                ->timeout(120)
                ->post($baseUrl.'/chat/completions', $payload);

            if (! $response->successful()) {
                throw new RuntimeException($this->friendlyProviderError('OpenRouter', $response->json(), $response->body()));
            }

            $lastJson = $response->json();
            $content = $this->extractOpenRouterText($lastJson);
            if (is_string($content) && trim($content) !== '') {
                break;
            }

            // Second try: more tokens, still no reasoning.
            $payload['max_tokens'] = max($maxTokens, 8192);
            $payload['reasoning'] = ['enabled' => false, 'exclude' => true];
        }

        if (! is_string($content) || trim($content) === '') {
            $finish = (string) data_get($lastJson, 'choices.0.finish_reason', 'unknown');
            $usage = data_get($lastJson, 'usage.completion_tokens', '?');

            throw new RuntimeException(
                'OpenRouter returned empty content (finish='.$finish.', completion_tokens='.$usage.'). '
                .'Free Nemotron often fails under load — wait 30–60s and retry, or switch model to openrouter/free or google/gemini-2.0-flash-exp:free in Configs → AI models.'
            );
        }

        return preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($content)) ?? trim($content);
    }

    /**
     * @param  array<string, mixed>|mixed  $json
     */
    private function extractOpenRouterText(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $message = data_get($json, 'choices.0.message');
        if (! is_array($message)) {
            return null;
        }

        $candidates = [
            $message['content'] ?? null,
            $message['reasoning'] ?? null,
            data_get($message, 'reasoning_details.0.text'),
            data_get($message, 'reasoning_details.0.summary'),
        ];

        foreach ($candidates as $candidate) {
            $text = $this->stringifyModelContent($candidate);
            if ($text !== null && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    private function stringifyModelContent(mixed $content): ?string
    {
        if (is_string($content)) {
            return $content;
        }

        if (! is_array($content)) {
            return null;
        }

        $text = '';
        foreach ($content as $part) {
            if (is_string($part)) {
                $text .= $part;
                continue;
            }
            if (! is_array($part)) {
                continue;
            }
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            } elseif (isset($part['content']) && is_string($part['content'])) {
                $text .= $part['content'];
            }
        }

        return $text === '' ? null : $text;
    }

    private function callClaude(string $model, string $mime, string $base64, ?string $apiKey = null): string
    {
        $apiKey = is_string($apiKey) && trim($apiKey) !== ''
            ? trim($apiKey)
            : config('services.anthropic.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException(
                'No Claude token. Paste the API token in Configs → AI models (or set ANTHROPIC_API_KEY in .env).'
            );
        }

        $mediaType = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ? $mime
            : 'image/png';

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])
            ->timeout(90)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => (int) config('warehouse.ai.max_tokens', 2000),
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mediaType,
                                    'data' => $base64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $this->prompt(),
                            ],
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->friendlyProviderError('Claude', $response->json(), $response->body()));
        }

        $blocks = data_get($response->json(), 'content', []);
        $text = '';
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (is_array($block) && ($block['type'] ?? null) === 'text') {
                    $text .= (string) ($block['text'] ?? '');
                }
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Claude returned empty content.');
        }

        // Strip optional markdown fences
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text)) ?? $text;

        return $text;
    }

    /**
     * @param  array<string, mixed>|mixed  $json
     */
    private function friendlyProviderError(string $provider, mixed $json, string $rawBody): string
    {
        $message = null;
        $code = null;

        if (is_array($json)) {
            $message = data_get($json, 'error.message');
            $code = data_get($json, 'error.code') ?? data_get($json, 'error.type');
            if (! is_string($message) || $message === '') {
                $message = data_get($json, 'message');
            }
        }

        $text = is_string($message) ? strtolower($message) : '';
        $codeText = is_string($code) ? strtolower($code) : '';

        if (
            str_contains($text, 'credit')
            || str_contains($text, 'quota')
            || str_contains($text, 'billing')
            || str_contains($text, 'balance')
            || str_contains($codeText, 'insufficient')
            || str_contains($codeText, 'credit')
        ) {
            if ($provider === 'Claude') {
                return 'Claude API has no credits. The Claude desktop/chat app is separate billing. Add API credits at https://console.anthropic.com/settings/billing — or switch Bids to OpenRouter free / OpenAI in Configs → AI models.';
            }

            if ($provider === 'OpenRouter') {
                return 'OpenRouter needs credits for this model, or the free model is rate-limited. Use a model id ending in :free (e.g. nvidia/nemotron-3.5-super-vl:free), wait and retry, or add credits at https://openrouter.ai/settings/credits';
            }

            return 'OpenAI API has no credits. Add billing at https://platform.openai.com/settings/organization/billing — or switch to OpenRouter free in Configs → AI models.';
        }

        if ($provider === 'OpenRouter' && (str_contains($text, 'rate') || str_contains($codeText, '429'))) {
            return 'OpenRouter free model rate limit hit. Wait a minute and try again, or use another :free model.';
        }

        if (is_string($message) && $message !== '') {
            return $provider.' error: '.$message;
        }

        return $provider.' request failed. Check the API token and model id in Configs → AI models.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(string $content): array
    {
        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('AI returned invalid JSON.');
        }

        $rows = $decoded['bids'] ?? $decoded;
        if (! is_array($rows)) {
            throw new RuntimeException('AI JSON missing bids array.');
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
            $rankRaw = $this->nullableString($row['bid_rank_raw'] ?? null);
            $totalBids = $this->nullableInt($row['total_bids'] ?? null);
            $myRank = $this->parseRankLabel($row['my_rank'] ?? null)
                ?? $this->parseRankLabel($rankRaw);

            if ($totalBids === null && $rankRaw !== null) {
                $totalBids = $this->parseTotalFromRankRaw($rankRaw);
            }

            $normalized[] = [
                'project_name' => $project,
                'total_bids' => $totalBids,
                'my_rank' => $myRank,
                'client_viewed' => false,
                'average_bid_raw' => $avgRaw,
                'average_bid' => $this->parseMoney($avgRaw),
                'my_bid_raw' => $myRaw,
                'my_bid' => $this->parseMoney($myRaw),
                'bid_type' => $this->normalizeBidType($row['bid_type'] ?? null)
                    ?? $this->detectBidType($myRaw)
                    ?? $this->detectBidType($avgRaw),
                'bid_placed_raw' => $placedRaw,
                'bid_placed_at' => BidPlacedTimeParser::parse(
                    $placedRaw,
                    now(),
                    (string) config('bids.screenshot_timezone', 'Asia/Kolkata'),
                ),
                'bid_rank_raw' => $rankRaw,
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

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));
            if (in_array($v, ['1', 'true', 'yes', 'viewed', 'y'], true)) {
                return true;
            }
            if (in_array($v, ['0', 'false', 'no', 'n'], true)) {
                return false;
            }
        }

        return null;
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

    /** Parse "#93 of 107 bids" → "93"; "#100+" → "100+". */
    private function parseRankLabel(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $text = trim((string) $raw);
        if (preg_match('/#\s*(\d+\+)/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/#\s*(\d+)/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d+\+)$/', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/^(\d+)$/', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    /** Parse "#93 of 107 bids" → 107. */
    private function parseTotalFromRankRaw(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }
        if (preg_match('/of\s+(\d+)\s*bids?/i', $raw, $m)) {
            return (int) $m[1];
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

    private function normalizeBidType(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }
        $v = strtolower(trim($value));

        return str_contains($v, 'hour') ? 'hourly' : (str_contains($v, 'fix') ? 'fixed' : null);
    }

    private function detectBidType(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return str_contains(strtolower($raw), 'hour') ? 'hourly' : 'fixed';
    }

}
