<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

/**
 * Finds practice/test question sets inside a synced pack and parses them.
 *
 * Two layouts are supported:
 *  - GS style:   practice/sets/DAY-NN-Q.md + DAY-NN-ANSWERS.md  (key DAY-NN)
 *  - File style: any question .md under practice/ or tests/ with a matching
 *                answers file (e.g. M01-practice.md + M01-answers.md).
 *                Key is derived from the relative path (practice_M01-practice).
 *
 * Existing DAY-NN behaviour is unchanged.
 */
class ExamPackSetFinder
{
    private const MAX_KEY_LENGTH = 60;

    public function __construct(private ExamMcqSetParser $parser) {}

    /**
     * @return list<array{key:string,label:string,kind:string,questions_abs:string,answers_abs:string|null,questions_rel:string,answers_rel:string|null,chapter:string|null}>
     */
    public function list(string $dir): array
    {
        $out = [...$this->daySources($dir), ...$this->fileSources($dir)];

        usort($out, fn ($a, $b) => strcmp($a['key'], $b['key']));

        return $out;
    }

    /**
     * @return array{key:string,label:string,kind:string,questions_abs:string,answers_abs:string|null,questions_rel:string,answers_rel:string|null,chapter:string|null}|null
     */
    public function find(string $dir, string $key): ?array
    {
        $key = trim($key);
        if (preg_match('/^DAY-\d+$/i', $key)) {
            $key = strtoupper($key);
        }

        foreach ($this->list($dir) as $source) {
            if ($source['key'] === $key) {
                return $source;
            }
        }

        return null;
    }

    /**
     * @param  array{kind:string,questions_abs:string,answers_abs:string|null,chapter:string|null}  $source
     * @return array{questions:list<array<string,mixed>>,answers:array<int,array<string,mixed>>}
     */
    public function parse(array $source): array
    {
        $qText = $this->readUtf8($source['questions_abs']);
        $aText = $source['answers_abs'] !== null && is_file($source['answers_abs'])
            ? $this->readUtf8($source['answers_abs'])
            : null;

        if ($source['kind'] === 'day') {
            return [
                'questions' => $this->parser->parseQuestions($qText),
                'answers' => $aText !== null ? $this->parser->parseAnswers($aText) : [],
            ];
        }

        return [
            'questions' => $this->parser->parseNumberedQuestions($qText, $source['chapter']),
            'answers' => $aText !== null ? $this->parser->parseNumberedAnswers($aText) : [],
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function daySources(string $dir): array
    {
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

            $out[] = [
                'key' => $key,
                'label' => $key,
                'kind' => 'day',
                'questions_abs' => $file->getPathname(),
                'answers_abs' => is_file($answersPath) ? $answersPath : null,
                'questions_rel' => 'practice/sets/'.$name,
                'answers_rel' => is_file($answersPath) ? 'practice/sets/'.$answersName : null,
                'chapter' => null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function fileSources(string $dir): array
    {
        $out = [];

        foreach (['practice', 'tests'] as $root) {
            $rootDir = $dir.DIRECTORY_SEPARATOR.$root;
            if (! is_dir($rootDir)) {
                continue;
            }

            foreach (File::allFiles($rootDir) as $file) {
                $name = $file->getFilename();
                if (strtolower($file->getExtension()) !== 'md') {
                    continue;
                }

                $relInRoot = str_replace('\\', '/', $file->getRelativePathname());
                if ($root === 'practice' && str_starts_with($relInRoot, 'sets/')) {
                    continue; // GS DAY sets are handled by daySources()
                }

                if ($this->isExcludedName($name)) {
                    continue;
                }

                $stem = pathinfo($name, PATHINFO_FILENAME);
                $folder = dirname($relInRoot);
                $folder = $folder === '.' ? '' : $folder;

                $answersAbs = $this->findAnswersFile($file->getPath(), $stem);
                $relNoExt = ($folder !== '' ? $folder.'/' : '').$stem;

                $out[] = [
                    'key' => $this->makeKey($root, $relNoExt),
                    'label' => $root.'/'.$relNoExt,
                    'kind' => 'file',
                    'questions_abs' => $file->getPathname(),
                    'answers_abs' => $answersAbs,
                    'questions_rel' => $root.'/'.$relInRoot,
                    'answers_rel' => $answersAbs !== null
                        ? $root.'/'.($folder !== '' ? $folder.'/' : '').basename($answersAbs)
                        : null,
                    'chapter' => $this->chapterFromName($stem),
                ];
            }
        }

        return $out;
    }

    private function isExcludedName(string $name): bool
    {
        if (preg_match('/(^|[-_])answers?\.md$/i', $name)) {
            return true;
        }

        return (bool) preg_match('/^(readme|agents|full-|.*result)/i', $name);
    }

    /**
     * Answer file for "X.md": X-answers.md, or the -practice → -answers rename,
     * or a folder-level ANSWERS.md (used by tests/mixed-120).
     */
    private function findAnswersFile(string $dir, string $stem): ?string
    {
        $candidates = [$stem.'-answers.md'];
        if (preg_match('/^(.*)-practice(.*)$/i', $stem, $m)) {
            $candidates[] = $m[1].'-answers'.$m[2].'.md';
        }
        $candidates[] = 'ANSWERS.md';

        foreach ($candidates as $candidate) {
            $path = $dir.DIRECTORY_SEPARATOR.$candidate;
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function makeKey(string $root, string $relNoExt): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '_', $root.'_'.$relNoExt) ?? '';
        $safe = trim($safe, '_-');

        if (strlen($safe) > self::MAX_KEY_LENGTH) {
            $safe = substr($safe, 0, self::MAX_KEY_LENGTH - 9).'-'.substr(md5($relNoExt), 0, 8);
        }

        return $safe;
    }

    /**
     * Read a pack file as valid UTF-8 (strip BOM, drop broken bytes) so the
     * /u regexes in the parser do not silently fail on bad input.
     */
    private function readUtf8(string $path): string
    {
        $value = (string) file_get_contents($path);
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }
        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            $value = is_string($converted) ? $converted : $value;
        }

        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

        return is_string($clean) ? $clean : '';
    }

    private function chapterFromName(string $stem): ?string
    {
        if (preg_match('/^(M\d{1,3})(?=[-_.]|$)/i', $stem, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }
}
