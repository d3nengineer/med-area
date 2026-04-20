<?php

declare(strict_types=1);

namespace Infrastructure\Services\Contracts;

use Domain\Analys\DTO\UserAnalysDTO;

interface AnalysSearchIndexServiceContract
{
    public function ensureIndex(): void;

    public function index(UserAnalysDTO $dto): void;

    public function delete(string $id): void;
}
