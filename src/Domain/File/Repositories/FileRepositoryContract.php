<?php

declare(strict_types=1);

namespace Domain\File\Repositories;

use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\DTO\FileDTO;
use Domain\File\Models\File;
use Illuminate\Database\Eloquent\Collection;
use Shared\Repositories\Contracts\BaseRepositoryContract;

/**
 * @method File create(FileDTO $file)
 */
interface FileRepositoryContract extends BaseRepositoryContract
{
    /**
     * Get many model File use filters
     *
     * @param FilterFileDTO $filters
     * @return Collection<array-key, File>
     */
    public function getMany(FilterFileDTO $filters): Collection;

    /**
     * Get a lightweight batch of files that are about to be soft-deleted.
     *
     * @param FilterFileDTO $filters
     * @param int $limit
     * @return Collection<array-key, File>
     */
    public function getDeletionBatch(FilterFileDTO $filters, int $limit): Collection;

    /**
     * Get a batch of files, including soft-deleted rows, for lifecycle backfill.
     *
     * @param int $limit
     * @param int $offset
     * @return Collection<array-key, File>
     */
    public function getBackfillBatch(int $limit, int $offset): Collection;

    /**
     * Soft delete from DB
     *
     * @param FilterFileDTO $filters
     * @return int
     */
    public function deleteMany(FilterFileDTO $filters): int;

    public function updateById(string $id, FileDTO $data): FileDTO;

    /**
     * Force delete from DB
     *
     * @param FilterFileDTO $filters
     * @return void
     */
    public function forceDeleteMany(FilterFileDTO $filters): void;
}
