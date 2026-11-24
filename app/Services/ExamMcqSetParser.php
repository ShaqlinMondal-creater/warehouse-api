<?php

namespace App\Services;

class ExamMcqSetParser
{
    /**
     * Parse DAY-*-Q.md into questions (no answers).
     *
     * @return list<array{no:int,id:string,chapter:string|null,stem:string,options:array<string,string>}>
     */
    public function parseQuestions(string $markdown): array
    {
        $parts = preg_split('/(?=^### Q\d+)/m', $markdown) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '' || ! preg_match('/^### Q(\d+)\s+`([^`]+)`/u', $part, $m)) {
                continue;
            }

            $no = (int) $m[1];
            $id = trim($m[2]);
            $chapter = null;
            if (preg_match('/\*\*\[([^\]]+)\]\*\*/u', $part, $cm)) {
                $chapter = trim($cm[1]);
            }

            $stem = '';
            if (preg_match('/\*\*\[[^\]]+\]\*\*\s*(.+?)(?=\nA\)|\nA\.)/su', $part, $sm)) {
                $stem = trim(preg_replace('/\s+/u', ' ', $sm[1]) ?? '');
            } elseif (preg_match('/^###[^\n]+\n+(.+?)(?=\nA\)|\nA\.)/su', $part, $sm2)) {
                $stem = trim(preg_replace('/\s+/u', ' ', preg_replace('/\*\*\[[^\]]+\]\*\*/u', '', $sm2[1]) ?? '') ?? '');
            }

            $options = [];
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                if (preg_match('/(?:^|\n)'.$letter.'\)\s*(.+?)(?=\n[A-D]\)|\n###|\z)/su', $part, $om)) {
                    $options[$letter] = trim(preg_replace('/\s+/u', ' ', $om[1]) ?? '');
                }
            }

            if ($stem === '' || count($options) < 2) {
                continue;
            }

            $out[] = [
                'no' => $no,
                'id' => $id,
                'chapter' => $chapter,
                'stem' => $stem,
                'options' => $options,
            ];
        }

        usort($out, fn ($a, $b) => $a['no'] <=> $b['no']);

        return $out;
    }

    /**
     * Parse DAY-*-ANSWERS.md table.
     *
     * @return array<int, array{no:int,id:string,answer:string,explanation:string}>
     */
    public function parseAnswers(string $markdown): array
    {
        $map = [];
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_starts_with($line, '|')) {
                continue;
            }
            if (preg_match('/^\|\s*Q\s*\|/iu', $line) || preg_match('/^\|\s*-+/', $line)) {
                continue;
            }

            $cells = array_values(array_filter(array_map('trim', explode('|', trim($line, '|'))), fn ($c) => $c !== ''));
            if (count($cells) < 3) {
                continue;
            }

            $no = (int) preg_replace('/\D+/', '', $cells[0]);
            if ($no < 1) {
                continue;
            }

            $id = $cells[1] ?? '';
            $answerRaw = $cells[2] ?? '';
            $explanation = $cells[3] ?? '';
            if (preg_match('/([A-D])/i', strip_tags($answerRaw), $am)) {
                $answer = strtoupper($am[1]);
            } else {
                continue;
            }

            $map[$no] = [
                'no' => $no,
                'id' => trim($id),
                'answer' => $answer,
                'explanation' => trim($explanation),
            ];
        }

        return $map;
    }

    /**
     * Parse plain numbered MCQ files (e.g. "1. stem" + "- (a) x (b) y").
     * Questions without at least two (a)-(d) options are skipped (open questions).
     *
     * @return list<array{no:int,id:string,chapter:string|null,stem:string,options:array<string,string>}>
     */
    public function parseNumberedQuestions(string $markdown, ?string $chapter = null): array
    {
        $blocks = [];
        $current = null;

        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            if (preg_match('/^(\d{1,3})\.\s+(.*)$/u', $line, $m)) {
                if ($current !== null) {
                    $blocks[] = $current;
                }
                $current = ['no' => (int) $m[1], 'lines' => [$m[2]]];

                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^(#{1,6}\s|-{3,}\s*$)/u', $line)) {
                $blocks[] = $current;
                $current = null;

                continue;
            }

            $current['lines'][] = $line;
        }

        if ($current !== null) {
            $blocks[] = $current;
        }

        $out = [];
        $seen = [];

        foreach ($blocks as $block) {
            $no = $block['no'];
            if (isset($seen[$no])) {
                continue;
            }

            $text = implode("\n", $block['lines']);
            if (! preg_match('/\([a-dA-D]\)/u', $text, $first, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $markerAt = $first[0][1];
            $stem = trim(preg_replace('/\s+/u', ' ', substr($text, 0, $markerAt)) ?? '');
            $stem = trim(preg_replace('/[\s\-]+$/u', '', $stem) ?? '');
            $rest = substr($text, $markerAt);

            $options = [];
            if (preg_match_all(
                '/\(([a-dA-D])\)[ \t]*(.*?)(?=[ \t]*(?:-[ \t]*)?\([a-dA-D]\)|\R|\z)/u',
                $rest,
                $om,
                PREG_SET_ORDER,
            )) {
                foreach ($om as $row) {
                    $letter = strtoupper($row[1]);
                    if (isset($options[$letter])) {
                        continue;
                    }
                    $options[$letter] = trim(preg_replace('/\s+/u', ' ', $row[2]) ?? '');
                }
            }

            if ($stem === '' || count($options) < 2) {
                continue;
            }

            $seen[$no] = true;
            $out[] = [
                'no' => $no,
                'id' => 'Q'.$no,
                'chapter' => $chapter,
                'stem' => $stem,
                'options' => $options,
            ];
        }

        usort($out, fn ($a, $b) => $a['no'] <=> $b['no']);

        return $out;
    }

    /**
     * Parse numbered answer keys. Supports:
     *   "1. (b) explanation"  and  "11. **(b) 1**"  (markdown lists)
     *   "1 c(10) · 2 c(0)"    (compact lines)
     *
     * @return array<int, array{no:int,id:string,answer:string,explanation:string}>
     */
    public function parseNumberedAnswers(string $markdown): array
    {
        $map = [];

        foreach (preg_split('/\R/u', $markdown) ?: [] as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(\d{1,3})\.\s*\*{0,2}\s*\(([a-dA-D])\)\s*\*{0,2}\s*(.*)$/u', $trimmed, $m)) {
                $no = (int) $m[1];
                if (! isset($map[$no])) {
                    $map[$no] = [
                        'no' => $no,
                        'id' => 'Q'.$no,
                        'answer' => strtoupper($m[2]),
                        'explanation' => trim(str_replace('**', '', $m[3])),
                    ];
                }

                continue;
            }

            if (preg_match('/^\d/u', $trimmed) && preg_match_all('/(?<![\d.])(\d{1,3})\s+([a-dA-D])(?![a-zA-Z])/u', $trimmed, $cm, PREG_SET_ORDER)) {
                foreach ($cm as $row) {
                    $no = (int) $row[1];
                    if (! isset($map[$no])) {
                        $map[$no] = [
                            'no' => $no,
                            'id' => 'Q'.$no,
                            'answer' => strtoupper($row[2]),
                            'explanation' => '',
                        ];
                    }
                }
            }
        }

        ksort($map);

        return $map;
    }
}
