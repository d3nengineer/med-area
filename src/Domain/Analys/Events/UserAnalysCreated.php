<?php

declare(strict_types=1);

namespace Domain\Analys\Events;

use Domain\Analys\DTO\UserAnalysDTO;

class UserAnalysCreated
{
    public function __construct(
        public readonly UserAnalysDTO $userAnalysDTO,
    ) {}
}
