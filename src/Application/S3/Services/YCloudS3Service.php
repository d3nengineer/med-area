<?php

declare(strict_types=1);

namespace Application\S3\Services;

use Application\S3\DTO\Responses\SignedFileResponseDTO;
use Application\S3\Services\Contracts\S3ServiceContract;
use Domain\File\DTO\FileDTO;
use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\Events\FileMarkedForDeletion;
use Domain\File\Models\File;
use Domain\File\Repositories\FileRepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Shared\Enums\Storage as EnumsStorage;
use Shared\Exceptions\ServerErrorException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Docs see here: https://yandex.cloud/ru/docs/storage/
 */
class YCloudS3Service implements S3ServiceContract
{
    public private(set) FilesystemAdapter $disk;

    protected readonly FileRepositoryContract $fileRepository;

    protected readonly EnumsStorage $diskName;

    public function __construct(
        FileRepositoryContract $fileRepository,
        ?FilesystemAdapter $disk = null,
        ?EnumsStorage $diskName = null,
    ) {
        $this->fileRepository = $fileRepository;
        $this->diskName = $diskName ?? EnumsStorage::S3;
        $this->disk = $disk ?? $this->resolveDisk($this->diskName);
    }

    public function upload(FileDTO $file): File
    {
        try {
            if ($file->emptyValue('content')) {
                throw new ServerErrorException('File content not found.');
            }

            assert($file->content instanceof UploadedFile);

            $path = $this->getFilePath($file);

            $result = $this->disk->putFile($path, $file->content);
            if (! $result) {
                throw new ServerErrorException('Cant upload file to ycloud s3. Path: ' . $path);
            }
        } catch (\Exception $e) {
            logger()->critical('[YCloudS3Service.upload] upload to S3 failed', [
                'error' => $e->getMessage(),
                'context' => ['file_key' => $file->key],
            ]);
            throw new ServerErrorException();
        }

        $file->key = $result;

        return $this->createFile($file);
    }

    public function createFile(FileDTO $file): File
    {
        try {
            return $this->fileRepository->create($file);
        } catch (\Throwable $e) {
            logger()->critical('[YCloudS3Service.createFile] failed to save file to DB', [
                'error' => $e->getMessage(),
                'context' => ['file_key' => $file->key],
            ]);

            throw new ServerErrorException();
        }
    }

    /**
     * Get Files Models Collection
     *
     * @param FilterFileDTO $filters
     * @return Collection<array-key, File>
     */
    public function getFiles(FilterFileDTO $filters): Collection
    {
        try {
            $result = $this->fileRepository->getMany($filters);
        } catch (\Throwable $e) {
            logger()->error('[YCloudS3Service.getFiles] failed to get files', [
                'error' => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);
            throw new ServerErrorException();
        }

        return $result;
    }

    /**
     * Get file content from s3 storage
     *
     * @param string $key
     * @param ?EnumsStorage $diskName = null (uses default disk if null)
     *
     * @return string
     * @throws NotFoundHttpException
     */
    public function getFileFromStorage(string $key, ?EnumsStorage $diskName = null): string
    {
        $storage = $diskName !== null ? $this->resolveDisk($diskName) : $this->disk;

        if (! $content = $storage->get($key)) {
            throw new NotFoundHttpException();
        }

        return $content;
    }

    public function temporaryUrl(string $key, \DateTimeInterface $expiresAt, ?EnumsStorage $diskName = null): string
    {
        $resolvedDiskName = $diskName ?? $this->diskName;
        $disk = $diskName !== null ? $this->resolveDisk($diskName) : $this->disk;

        try {
            if (! $disk->providesTemporaryUrls()) {
                throw new RuntimeException('Temporary URLs are not supported for disk: ' . $resolvedDiskName->value);
            }

            return $disk->temporaryUrl($key, $expiresAt);
        } catch (\Throwable $e) {
            logger()->error('[YCloudS3Service.temporaryUrl] failed to generate temporary url', [
                'error' => $e->getMessage(),
                'context' => [
                    'key' => $key,
                    'disk' => $resolvedDiskName->value,
                    'expires_at' => $expiresAt->format(\DateTimeInterface::ATOM),
                ],
            ]);

            throw new ServerErrorException();
        }
    }

    public function toSignedResponse(File $file): SignedFileResponseDTO
    {
        $expiresAt = now()->addMinutes($this->getSignedUrlTtlMinutes());

        return SignedFileResponseDTO::from([
            'id' => $file->id,
            'user_id' => $file->user_id,
            'download_url' => $this->temporaryUrl($file->key, $expiresAt, $file->storage),
            'download_expires_at' => $expiresAt,
        ]);
    }

    public function delete(FilterFileDTO $filters): void
    {
        logger()->info('[YCloudS3Service.delete] deleting files', ['filters' => $filters->toArray()]);

        try {
            $this->fileRepository->deleteMany($filters);
        } catch (\Throwable $e) {
            logger()->error('[YCloudS3Service.delete] delete failed', [
                'error' => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);
            throw new ServerErrorException();
        }
    }

    public function forceDelete(FilterFileDTO $filters): void
    {
        logger()->info('[YCloudS3Service.forceDelete] force-deleting files', ['filters' => $filters->toArray()]);

        try {
            $filesForDeleting = $this->fileRepository->getMany($filters);

            $this->fileRepository->forceDeleteMany($filters);

            foreach ($filesForDeleting as $file) {
                event(new FileMarkedForDeletion($file->key, $this->diskName));
            }
        } catch (\Throwable $e) {
            logger()->error('[YCloudS3Service.forceDelete] force-delete failed', [
                'error' => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);
            throw new ServerErrorException();
        }
    }

    public function fileExists(string $key): bool
    {
        return $this->disk->exists($key);
    }

    public function setDisk(FilesystemAdapter $newDisk): self
    {
        $this->disk = $newDisk;

        return $this;
    }

    /**
     * Get path for file
     * Example: users/{userId}/fileName.extension
     *
     * @param FileDTO $file
     * @param string|null $userId
     * @return string
     *
     * @throws ServerErrorException
     */
    private function getFilePath(FileDTO $file, ?string $userId = null): string
    {
        if ($userId === null && ! auth()->check()) {
            throw new ServerErrorException();
        }

        $userId ??= auth()->user()?->id;

        assert($file->content instanceof UploadedFile);

        return 'users/' . $userId . '/' . $file->key . '.' . $file->content->extension();
    }

    private function getSignedUrlTtlMinutes(): int
    {
        return (int) config('filesystems.environments.signed_url_ttl_minutes', 5);
    }

    private function resolveDisk(EnumsStorage $diskName): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($diskName->value);

        return $disk;
    }
}
