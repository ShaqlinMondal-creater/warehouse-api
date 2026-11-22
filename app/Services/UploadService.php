<?php

namespace App\Services;

use App\Models\Upload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadService
{
    /**
     * Replace the single upload for a collection (e.g. profile avatar).
     */
    public function replace(
        Model $uploadable,
        int $userId,
        string $collection,
        UploadedFile $file,
        string $folder,
    ): Upload {
        return DB::transaction(function () use ($uploadable, $userId, $collection, $file, $folder) {
            $this->clearCollection($uploadable, $collection);

            return $this->store($uploadable, $userId, $collection, $file, $folder, 1);
        });
    }

    /**
     * Append an image to a multi-image collection (project gallery).
     * sort_order becomes next integer: 1, 2, 3…
     */
    public function append(
        Model $uploadable,
        int $userId,
        string $collection,
        UploadedFile $file,
        string $folder,
    ): Upload {
        $next = (int) $uploadable->uploads()
            ->where('collection', $collection)
            ->max('sort_order');

        return $this->store($uploadable, $userId, $collection, $file, $folder, $next + 1);
    }

    /**
     * Reorder gallery images. Pass upload IDs in desired order → sort_order 1..n.
     *
     * @param  list<int>  $orderedIds
     */
    public function reorder(Model $uploadable, string $collection, array $orderedIds): void
    {
        DB::transaction(function () use ($uploadable, $collection, $orderedIds) {
            $owned = $uploadable->uploads()
                ->where('collection', $collection)
                ->whereIn('id', $orderedIds)
                ->pluck('id')
                ->all();

            $ownedSet = array_flip($owned);
            $position = 1;

            foreach ($orderedIds as $id) {
                if (! isset($ownedSet[$id])) {
                    continue;
                }

                Upload::query()->whereKey($id)->update(['sort_order' => $position]);
                $position++;
            }
        });
    }

    public function clearCollection(Model $uploadable, string $collection): void
    {
        $uploadable->uploads()
            ->where('collection', $collection)
            ->get()
            ->each(fn (Upload $upload) => $upload->delete());
    }

    private function store(
        Model $uploadable,
        int $userId,
        string $collection,
        UploadedFile $file,
        string $folder,
        int $sortOrder,
    ): Upload {
        $path = $file->store($folder, 'public');

        return Upload::query()->create([
            'user_id' => $userId,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: null,
            'collection' => $collection,
            'uploadable_type' => $uploadable->getMorphClass(),
            'uploadable_id' => $uploadable->getKey(),
            'sort_order' => $sortOrder,
        ]);
    }
}
