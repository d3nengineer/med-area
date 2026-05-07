<?php

declare(strict_types=1);

namespace Domain\File\Events;

use Domain\File\DTO\FileDTO;

class FileUploaded
{
    public function __construct(
        public readonly FileDTO $fileDTO,
    ) {}
}
