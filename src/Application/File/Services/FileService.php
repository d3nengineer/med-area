<?php

declare(strict_types=1);

namespace Application\File\Services;

use Application\File\Services\Contracts\FileServiceContract;
use Application\S3\DTO\Requests\CreateFilesRequestDTO;
use Application\S3\DTO\Responses\SignedFileResponseDTO;
use Application\S3\Services\Contracts\S3ServiceContract;
use Domain\File\DTO\FileDTO;
use Domain\File\DTO\Filters\FilterFileDTO;
use Shared\Enums\Storage as EnumsStorage;

class FileService implements FileServiceContract
{
    public function __construct(
        private readonly S3ServiceContract $s3Service,
    ) {}

    public function upload(CreateFilesRequestDTO $dto): array
    {
        logger()->info('[FileService.upload] uploading files', [
            'files_count' => is_array($dto->files) ? count($dto->files) : 0,
        ]);

        $responses = [];

        if (! is_array($dto->files)) {
            return $responses;
        }

        foreach ($dto->files as $file) {
            $responses[] = $this->uploadSingleFile($file);
        }

        return $responses;
    }

    public function index(FilterFileDTO $filters): array
    {
        logger()->info('[FileService.index] listing files', [
            'filters' => $filters->toArray(),
        ]);

        $responses = [];
        $files = $this->s3Service->getFiles($filters);

        foreach ($files as $file) {
            $responses[] = $this->s3Service->toSignedResponse($file);
        }

        return $responses;
    }

    public function delete(FilterFileDTO $filters): void
    {
        logger()->info('[FileService.delete] deleting files', [
            'filters' => $filters->toArray(),
        ]);

        $this->s3Service->delete($filters);
    }

    private function uploadSingleFile(FileDTO $file): SignedFileResponseDTO
    {
        $file->storage = EnumsStorage::S3;

        return $this->s3Service->toSignedResponse(
            $this->s3Service->upload($file)
        );
    }
}
