<?php

declare(strict_types=1);

namespace Application\Analys\Services\Contracts;

use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Illuminate\Support\Collection;

interface UserAnalysSearchServiceContract
{
    /**
     * Search user analyses.
     *
     * @param SearchUserAnalysDTO $dto
     * @return Collection<int, UserAnalysDTO>
     */
    public function search(SearchUserAnalysDTO $dto): Collection;
}
