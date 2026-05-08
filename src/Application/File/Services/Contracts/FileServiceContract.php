<?php

declare(strict_types=1);

namespace Application\File\Services\Contracts;

use Application\S3\DTO\Requests\CreateFilesRequestDTO;
use Application\S3\DTO\Responses\SignedFileResponseDTO;
use Domain\File\DTO\Filters\FilterFileDTO;

interface FileServiceContract
{
    /**
     * @return array<int, SignedFileResponseDTO>
     */
    public function upload(CreateFilesRequestDTO $dto): array;

    /**
     * @return array<int, SignedFileResponseDTO>
     */
    public function index(FilterFileDTO $filters): array;

    public function delete(FilterFileDTO $filters): void;
}
