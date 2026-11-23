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
}
