<?php

namespace App\Services;

use App\Models\ExamSet;
use App\Models\ExamSubject;
use App\Models\ExamSubjectState;
use App\Models\ExamSync;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ExamPackSyncService
{
    private const MAX_BYTES = 52_428_800; // 50 MB

    /** @var list<string> */
    private const ALLOWED_EXTENSIONS = ['md', 'json', 'txt', 'csv'];

    public function syncSubjectZip(User $user, ExamSet $exam, ExamSubject $subject, UploadedFile $zip): ExamSync
    {
        if ($subject->exam_set_id !== $exam->id) {
            throw new RuntimeException('Subject does not belong to this exam.');
        }
        if (! $exam->is_enabled || ! $subject->is_enabled) {
            throw new RuntimeException('This exam subject is not enabled.');
        }

        $original = $zip->getClientOriginalName() ?: 'pack.zip';
        $bytes = (int) $zip->getSize();

        if ($bytes <= 0 || $bytes > self::MAX_BYTES) {
            return $this->failSync($user, $subject, $original, max(0, $bytes), 'Zip must be between 1 byte and 50 MB.');
        }

        $mime = (string) ($zip->getMimeType() ?: '');
        $ext = strtolower($zip->getClientOriginalExtension() ?: pathinfo($original, PATHINFO_EXTENSION));
        if ($ext !== 'zip' && ! str_contains($mime, 'zip')) {
            return $this->failSync($user, $subject, $original, $bytes, 'Only .zip files are allowed.');
        }

        $relativeBase = 'exams/'.$user->id.'/'.$exam->slug.'/'.$subject->slug;
        $absoluteBase = storage_path('app/private/'.$relativeBase);
        $incomingRel = $relativeBase.'/_incoming_'.Str::lower(Str::random(8));
        $incomingAbs = storage_path('app/private/'.$incomingRel);
        $currentRel = $relativeBase.'/current';
        $currentAbs = storage_path('app/private/'.$currentRel);
        $tmpZip = $incomingAbs.'.zip';

        File::ensureDirectoryExists(dirname($incomingAbs));

        try {
            $zip->move(dirname($tmpZip), basename($tmpZip));
            File::ensureDirectoryExists($incomingAbs);

            $indexed = $this->extractZipSafely($tmpZip, $incomingAbs);
            if ($indexed === 0) {
                throw new RuntimeException('Zip had no allowed study files (.md / .json / .txt / .csv).');
            }

            $progress = $this->readProgress($incomingAbs);

            // Atomic-ish swap: incoming → current
            $backupAbs = $absoluteBase.'/_previous_'.Str::lower(Str::random(6));
            if (is_dir($currentAbs)) {
                File::moveDirectory($currentAbs, $backupAbs);
            }
            File::moveDirectory($incomingAbs, $currentAbs);
            if (is_dir($backupAbs)) {
                File::deleteDirectory($backupAbs);
            }

            @unlink($tmpZip);

            return DB::transaction(function () use ($user, $subject, $original, $bytes, $currentRel, $indexed, $progress) {
                $sync = ExamSync::query()->create([
                    'user_id' => $user->id,
                    'exam_subject_id' => $subject->id,
                    'original_name' => $original,
                    'bytes' => $bytes,
                    'status' => 'ok',
                    'storage_path' => $currentRel,
                    'files_indexed' => $indexed,
                    'message' => 'Extracted and synced.',
                    'progress' => $progress,
                    'synced_at' => now(),
                ]);

                ExamSubjectState::query()->updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'exam_subject_id' => $subject->id,
                    ],
                    [
                        'storage_path' => $currentRel,
                        'last_synced_at' => now(),
                        'last_original_name' => $original,
                        'last_bytes' => $bytes,
                        'file_count' => $indexed,
                        'progress' => $progress,
                    ],
                );

                return $sync;
            });
        } catch (Throwable $e) {
            if (is_dir($incomingAbs)) {
                File::deleteDirectory($incomingAbs);
            }
            if (is_file($tmpZip)) {
                @unlink($tmpZip);
            }

            return $this->failSync($user, $subject, $original, $bytes, $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function subjectSummary(User $user, ExamSet $exam, ExamSubject $subject): array
    {
        if ($subject->exam_set_id !== $exam->id) {
            throw new RuntimeException('Subject does not belong to this exam.');
        }

        $state = ExamSubjectState::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->first();

        $dir = $state?->storage_path
            ? storage_path('app/private/'.$state->storage_path)
            : null;

        $files = [
            'notes' => [],
            'practice' => [],
            'tests' => [],
            'root' => [],
        ];
        $progress = $state?->progress;

        if ($dir && is_dir($dir)) {
            $files = [
                'notes' => $this->listMarkdown($dir.DIRECTORY_SEPARATOR.'notes'),
                'practice' => $this->listMarkdown($dir.DIRECTORY_SEPARATOR.'practice', recursive: true),
                'tests' => $this->listMarkdown($dir.DIRECTORY_SEPARATOR.'tests'),
                'root' => $this->listRootMeta($dir),
            ];
            $fresh = $this->readProgress($dir);
            if ($fresh !== null) {
                $progress = $fresh;
            }
        }

        $recentSyncs = ExamSync::query()
            ->where('user_id', $user->id)
            ->where('exam_subject_id', $subject->id)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'original_name', 'bytes', 'status', 'files_indexed', 'message', 'synced_at', 'created_at']);

        return [
            'exam' => [
                'id' => $exam->id,
                'slug' => $exam->slug,
                'title' => $exam->title,
                'year' => $exam->year,
            ],
            'subject' => [
                'id' => $subject->id,
                'slug' => $subject->slug,
                'title' => $subject->title,
            ],
            'synced' => (bool) ($state?->storage_path && $dir && is_dir($dir)),
            'storage_path' => $state?->storage_path,
            'last_synced_at' => $state?->last_synced_at?->toIso8601String(),
            'last_original_name' => $state?->last_original_name,
            'last_bytes' => $state?->last_bytes,
            'file_count' => $state?->file_count ?? 0,
            'progress' => $progress,
            'files' => $files,
            'recent_syncs' => $recentSyncs,
            'read_at' => now()->toIso8601String(),
        ];
    }

    private function failSync(User $user, ExamSubject $subject, string $original, int $bytes, string $message): ExamSync
    {
        return ExamSync::query()->create([
            'user_id' => $user->id,
            'exam_subject_id' => $subject->id,
            'original_name' => $original,
            'bytes' => $bytes,
            'status' => 'failed',
            'storage_path' => null,
            'files_indexed' => 0,
            'message' => $message,
            'progress' => null,
            'synced_at' => null,
        ]);
    }

    /**
     * Extract zip with ZipSlip protection and extension allow-list.
     * Unwraps a single top-level folder when present (e.g. general-studies/...).
     */
    private function extractZipSafely(string $zipPath, string $targetAbs): int
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Could not open zip archive.');
        }

        $staging = $targetAbs.DIRECTORY_SEPARATOR.'_raw';
        File::ensureDirectoryExists($staging);
        $stagingReal = realpath($staging);
        if ($stagingReal === false) {
            $zip->close();
            throw new RuntimeException('Could not prepare extract folder.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }
                $name = str_replace('\\', '/', $name);
                if ($name === '' || str_ends_with($name, '/')) {
                    continue;
                }
                if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:/#', $name)) {
                    throw new RuntimeException('Zip contains an unsafe path.');
                }

                $base = basename($name);
                if ($base === '' || str_starts_with($base, '.')) {
                    continue;
                }
                $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
                if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    continue;
                }

                $dest = $stagingReal.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $name);
                $destDir = dirname($dest);
                File::ensureDirectoryExists($destDir);

                $destRealDir = realpath($destDir);
                if ($destRealDir === false || ! str_starts_with($destRealDir, $stagingReal)) {
                    throw new RuntimeException('Zip path escaped target directory.');
                }

                $stream = $zip->getStream($name);
                if ($stream === false) {
                    continue;
                }
                $out = fopen($dest, 'wb');
                if ($out === false) {
                    fclose($stream);
                    throw new RuntimeException('Could not write extracted file.');
                }
                stream_copy_to_stream($stream, $out);
                fclose($out);
                fclose($stream);
            }
        } finally {
            $zip->close();
        }

        $source = $this->normalizeExtractRoot($stagingReal);
        $count = 0;
        foreach (File::allFiles($source) as $file) {
            $rel = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $dest = $targetAbs.DIRECTORY_SEPARATOR.$rel;
            File::ensureDirectoryExists(dirname($dest));
            File::move($file->getPathname(), $dest);
            $count++;
        }
        File::deleteDirectory($staging);

        return $count;
    }

    private function normalizeExtractRoot(string $stagingReal): string
    {
        $entries = array_values(array_filter(scandir($stagingReal) ?: [], fn ($e) => $e !== '.' && $e !== '..'));
        // If the zip wrapped a single root folder (common when zipping a subject dir), unwrap it.
        if (count($entries) === 1) {
            $only = $stagingReal.DIRECTORY_SEPARATOR.$entries[0];
            if (is_dir($only)) {
                return $only;
            }
        }

        return $stagingReal;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readProgress(string $dir): ?array
    {
        $path = $dir.DIRECTORY_SEPARATOR.'progress.json';
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return list<array{name: string, relative: string, size: int, modified_at: string|null}>
     */
    private function listMarkdown(string $dir, bool $recursive = false): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $files = $recursive ? File::allFiles($dir) : File::files($dir);
        $out = [];
        foreach ($files as $file) {
            $name = $file->getFilename();
            if (str_starts_with($name, '.')) {
                continue;
            }
            if (! preg_match('/\.(md|json|txt|csv)$/i', $name)) {
                continue;
            }
            $relative = ltrim(str_replace($dir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $out[] = [
                'name' => $name,
                'relative' => str_replace('\\', '/', $relative),
                'size' => $file->getSize(),
                'modified_at' => date('c', $file->getMTime() ?: time()),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['relative'], $b['relative']));

        return $out;
    }

    /**
     * @return list<array{name: string, size: int, modified_at: string|null}>
     */
    private function listRootMeta(string $dir): array
    {
        $names = ['progress.json', 'roadmap.md', 'mistakes.md', 'revision.md', 'AGENTS.md'];
        $out = [];
        foreach ($names as $name) {
            $path = $dir.DIRECTORY_SEPARATOR.$name;
            if (! is_file($path)) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'size' => filesize($path) ?: 0,
                'modified_at' => date('c', filemtime($path) ?: time()),
            ];
        }

        return $out;
    }
}
