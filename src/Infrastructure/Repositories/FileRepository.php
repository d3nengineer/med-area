<?php

declare(strict_types=1);

namespace Infrastructure\Repositories;

use Domain\File\DTO\FileDTO;
use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\Models\File;
use Domain\File\Repositories\FileRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Shared\DTO\FilterBaseDTO;
use Shared\Exceptions\ServerErrorException;
use Shared\Repositories\BaseRepository;

class FileRepository extends BaseRepository implements FileRepositoryContract
{
    /**
     * @var class-string<File>
     */
    protected string $model = File::class;

    /**
     * Get many model File use filters
     *
     * @param FilterFileDTO $filters
     * @return Collection<array-key, File>
     */
    public function getMany(FilterFileDTO $filters): Collection
    {
        $query = $filters->emptyValue('min_deleted_at') && $filters->emptyValue('max_deleted_at')
            ? $this->model::query()
            : $this->model::withTrashed();

        $query = $this->baseFilters($query, $filters);

        $result = $query->get();

        return $result;
    }

    /**
     * Get a lightweight batch of files scheduled for soft deletion.
     *
     * @param FilterFileDTO $filters
     * @param int $limit
     * @return Collection<array-key, File>
     */
    public function getDeletionBatch(FilterFileDTO $filters, int $limit): Collection
    {
        $query = $this->baseFilters($this->model::query(), $filters);

        return $query
            ->select(['id', 'user_id', 'storage', 'key'])
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function getBackfillBatch(int $limit, int $offset): Collection
    {
        return $this->model::query()
            ->withTrashed()
            ->orderBy('id')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Soft delete from DB
     *
     * @param FilterFileDTO $filters
     * @return int
     */
    public function deleteMany(FilterFileDTO $filters): int
    {
        logger()->info('[FileRepository.deleteMany] deleting files', ['filters' => $filters->toArray()]);

        $query = $this->model::query();

        try {
            return $this->baseFilters($query, $filters)->delete();
        } catch (\Throwable $e) {
            logger()->error('[FileRepository.deleteMany] DB operation failed', [
                'error'   => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);
            throw new ServerErrorException($e->getMessage());
        }
    }

    public function updateById(string $id, FileDTO $data): FileDTO
    {
        try {
            /** @var File $model */
            $model = $this->model::query()->withTrashed()->findOrFail($id);

            $model->updateOrFail($data->toArray());

            return FileDTO::from($model->refresh());
        } catch (\Throwable $e) {
            logger()->error('[FileRepository.updateById] DB operation failed', [
                'error' => $e->getMessage(),
                'context' => ['id' => $id],
            ]);

            throw new ServerErrorException($e->getMessage());
        }
    }

    /**
     * Force delete from DB
     *
     * @param FilterFileDTO $filters
     * @return void
     */
    public function forceDeleteMany(FilterFileDTO $filters): void
    {
        logger()->info('[FileRepository.forceDeleteMany] force-deleting files', ['filters' => $filters->toArray()]);

        $query = $this->model::onlyTrashed();

        try {
            $this->baseFilters($query, $filters)->forceDelete();
        } catch (\Throwable $e) {
            logger()->error('[FileRepository.forceDeleteMany] DB operation failed', [
                'error'   => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);
            throw new ServerErrorException($e->getMessage());
        }
    }

    /**
     * Base filters for sql requests
     *
     * @param Builder<File> $query
     * @param FilterFileDTO $filters
     * @return Builder<File>
     */
    public function baseFilters(Builder $query, FilterBaseDTO $filters): Builder
    {
        $query = parent::baseFilters($query, $filters);

        // Attribute: id
        if ($filters->isNotEmptyValue('ids') && ! empty($filters->ids)) {
            $query->whereIn('id', $filters->ids);
        }

        // Attribute: deleted_at
        $filters->min_deleted_at = $filters->emptyValue('min_deleted_at') ? null : $filters->min_deleted_at;
        $filters->max_deleted_at = $filters->emptyValue('max_deleted_at') ? null : $filters->max_deleted_at;

        /** @var \Carbon\Carbon|null $minDeletedAt */
        $minDeletedAt = $filters->min_deleted_at;
        /** @var \Carbon\Carbon|null $maxDeletedAt */
        $maxDeletedAt = $filters->max_deleted_at;

        $query = $this->filterDateRange($query, 'deleted_at', $minDeletedAt, $maxDeletedAt);

        // Attribute: user_id
        if ($filters->isNotEmptyValue('user_ids') && ! empty($filters->user_ids)) {
            $query->whereIn('user_id', $filters->user_ids);
        }

        // Attribute: lifecycle_state
        if (is_array($filters->lifecycle_states) && $filters->lifecycle_states !== []) {
            $lifecycleStates = array_map(
                static fn (mixed $state): string => $state instanceof \BackedEnum ? $state->value : (string) $state,
                $filters->lifecycle_states,
            );

            $query->whereIn('lifecycle_state', $lifecycleStates);
        }

        // Attribute: storage_operation_id
        if (is_array($filters->storage_operation_ids) && $filters->storage_operation_ids !== []) {
            $query->whereIn('storage_operation_id', $filters->storage_operation_ids);
        }

        // Attribute: size
        if ($filters->isNotEmptyValue('min_size') && $filters->emptyValue('max_size')) {
            $query->where('size', '>', $filters->min_size);
        }

        if ($filters->emptyValue('min_size') && $filters->isNotEmptyValue('max_size')) {
            $query->where('size', '<', $filters->max_size);
        }

        if ($filters->isNotEmptyValue('min_size') && $filters->isNotEmptyValue('max_size')) {
            $query
                ->where('size', '>', $filters->min_size)
                ->where('size', '<', $filters->max_size);
        }

        // Attribute: lifecycle_changed_at
        $filters->min_lifecycle_changed_at = $filters->emptyValue('min_lifecycle_changed_at')
            ? null
            : $filters->min_lifecycle_changed_at;
        $filters->max_lifecycle_changed_at = $filters->emptyValue('max_lifecycle_changed_at')
            ? null
            : $filters->max_lifecycle_changed_at;

        /** @var \Carbon\Carbon|null $minLifecycleChangedAt */
        $minLifecycleChangedAt = $filters->min_lifecycle_changed_at;
        /** @var \Carbon\Carbon|null $maxLifecycleChangedAt */
        $maxLifecycleChangedAt = $filters->max_lifecycle_changed_at;

        $query = $this->filterDateRange(
            $query,
            'lifecycle_changed_at',
            $minLifecycleChangedAt,
            $maxLifecycleChangedAt,
        );

        return $query;
    }
}
