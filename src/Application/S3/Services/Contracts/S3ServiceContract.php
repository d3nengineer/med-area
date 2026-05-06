<?php

declare(strict_types=1);

namespace Application\S3\Services\Contracts;

use Application\S3\DTO\Responses\SignedFileResponseDTO;
use DateTimeInterface;
use Domain\File\DTO\FileDTO;
use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\Models\File;
use Illuminate\Database\Eloquent\Collection;
use Shared\Enums\Storage as EnumsStorage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

interface S3ServiceContract
{
    public function upload(FileDTO $file): File;

    public function createFile(FileDTO $file): File;

    /**
     * Get Files Models Collection
     *
     * @param FilterFileDTO $filters
     * @return Collection<array-key, File>
     */
    public function getFiles(FilterFileDTO $filters): Collection;

    /**
     * Get file content from s3 storage
     *
     * @param string $key
     * @param EnumsStorage|null $diskName (uses default disk if null)
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function getFileFromStorage(string $key, ?EnumsStorage $diskName = null): string;

    public function temporaryUrl(string $key, DateTimeInterface $expiresAt, ?EnumsStorage $diskName = null): string;

    public function toSignedResponse(File $file): SignedFileResponseDTO;

    public function delete(FilterFileDTO $filters): void;

    public function forceDelete(FilterFileDTO $filters): void;

    public function fileExists(string $key): bool;
}
