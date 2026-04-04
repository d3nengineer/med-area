<?php

declare(strict_types=1);

namespace Domain\Analys\DTO\Filters;

use Shared\DTO\BaseDTO;

class SearchUserAnalysDTO extends BaseDTO
{
    public function __construct(
        public readonly string $query,
        public readonly string $userId,
        public readonly int $limit = 20,
        public readonly int $offset = 0,
    ) {}
}
