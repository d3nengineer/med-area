<?php

declare(strict_types=1);

namespace Infrastructure\Services\Contracts;

use Domain\Audit\DTO\UserActivityAuditDTO;

interface UserActivityAuditIndexServiceContract
{
    public function ensureIndex(): void;

    public function index(UserActivityAuditDTO $dto): void;
}
