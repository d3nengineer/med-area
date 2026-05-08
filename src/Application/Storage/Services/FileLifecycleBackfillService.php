<?php

declare(strict_types=1);

namespace Application\Storage\Services;

use Application\Storage\DTO\BackfillFileLifecycleReportDTO;
use Application\Storage\Services\Contracts\FileLifecycleBackfillServiceContract;
use Carbon\CarbonInterface;
use Domain\File\DTO\FileDTO;
use Domain\File\Enums\FileLifecycleState;
use Domain\File\Models\File;
use Domain\File\Repositories\FileRepositoryContract;

class FileLifecycleBackfillService implements FileLifecycleBackfillServiceContract
{
    public function __construct(
        private readonly FileRepositoryContract $fileRepository,
    ) {}

    public function backfill(bool $apply, int $chunkSize = 200): BackfillFileLifecycleReportDTO
    {
        $offset = 0;
        $scannedCount = 0;
        $updatedFileIds = [];
        $manualReviewFileIds = [];

        do {
            $files = $this->fileRepository->getBackfillBatch($chunkSize, $offset);

            foreach ($files as $file) {
                ++$scannedCount;

                $decision = $this->resolveBackfillPayload($file);

                if ($decision['manual_review']) {
                    $manualReviewFileIds[] = $file->id;

                    continue;
                }

                if ($decision['payload'] === null) {
                    continue;
                }

                $updatedFileIds[] = $file->id;

                if ($apply) {
                    $this->fileRepository->updateById($file->id, FileDTO::from($decision['payload']));
                }
            }

            $offset += $files->count();
        } while ($files->isNotEmpty());

        return BackfillFileLifecycleReportDTO::from([
            'dry_run' => ! $apply,
            'scanned_count' => $scannedCount,
            'updated_count' => count($updatedFileIds),
            'manual_review_count' => count($manualReviewFileIds),
            'updated_file_ids' => $updatedFileIds,
            'manual_review_file_ids' => $manualReviewFileIds,
        ]);
    }

    /**
     * @return array{manual_review: bool, payload: array<string, mixed>|null}
     */
    private function resolveBackfillPayload(File $file): array
    {
        if ($file->deleted_at !== null) {
            if ($file->lifecycle_state === FileLifecycleState::FAILED) {
                return ['manual_review' => true, 'payload' => null];
            }

            $payload = [
                'lifecycle_state' => FileLifecycleState::DELETING,
                'delete_requested_at' => $file->delete_requested_at ?? $file->deleted_at,
                'lifecycle_changed_at' => $file->lifecycle_changed_at ?? $file->deleted_at,
                'storage_reconciled_at' => $file->storage_reconciled_at ?? now(),
            ];

            return [
                'manual_review' => false,
                'payload' => $this->diffPayload($file, $payload),
            ];
        }

        if ($file->lifecycle_state !== FileLifecycleState::AVAILABLE) {
            return ['manual_review' => true, 'payload' => null];
        }

        $payload = [
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'lifecycle_changed_at' => $file->lifecycle_changed_at ?? $file->created_at,
            'storage_reconciled_at' => $file->storage_reconciled_at ?? now(),
            'upload_completed_at' => $file->upload_completed_at ?? $file->created_at,
        ];

        return [
            'manual_review' => false,
            'payload' => $this->diffPayload($file, $payload),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function diffPayload(File $file, array $payload): ?array
    {
        $changes = [];

        foreach ($payload as $key => $value) {
            $currentValue = $file->{$key};

            if ($this->valuesMatch($currentValue, $value)) {
                continue;
            }

            $changes[$key] = $value;
        }

        return $changes === [] ? null : $changes;
    }

    private function valuesMatch(mixed $currentValue, mixed $nextValue): bool
    {
        if ($currentValue instanceof CarbonInterface && $nextValue instanceof CarbonInterface) {
            return $currentValue->equalTo($nextValue);
        }

        if ($currentValue instanceof \BackedEnum && $nextValue instanceof \BackedEnum) {
            return $currentValue->value === $nextValue->value;
        }

        if ($currentValue instanceof \BackedEnum && is_string($nextValue)) {
            return $currentValue->value === $nextValue;
        }

        if (is_string($currentValue) && $nextValue instanceof \BackedEnum) {
            return $currentValue === $nextValue->value;
        }

        return $currentValue === $nextValue;
    }
}
