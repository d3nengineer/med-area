<?php

declare(strict_types=1);

namespace Domain\Analys\Repositories;

use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Illuminate\Support\Collection;

interface UserAnalysSearchRepositoryContract
{
    /**
     * Search user analyses in Elasticsearch.
     *
     * @param SearchUserAnalysDTO $dto
     * @return Collection<int, UserAnalysDTO>
     */
    public function search(SearchUserAnalysDTO $dto): Collection;
}
