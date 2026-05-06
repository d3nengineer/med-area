<?php

declare(strict_types=1);

namespace Application\Analys\Services;

use Application\Analys\Services\Contracts\UserAnalysSearchServiceContract;
use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Domain\Analys\Repositories\UserAnalysSearchRepositoryContract;
use Illuminate\Support\Collection;
use Shared\Exceptions\ServerErrorException;

class UserAnalysSearchService implements UserAnalysSearchServiceContract
{
    public function __construct(
        private readonly UserAnalysSearchRepositoryContract $searchRepository,
    ) {}

    public function search(SearchUserAnalysDTO $dto): Collection
    {
        try {
            $results = $this->searchRepository->search($dto);
        } catch (\Throwable $e) {
            logger()->error('[UserAnalysSearchService.search] Elasticsearch search failed', [
                'userId'  => $dto->userId,
                'query'   => $dto->query,
                'message' => $e->getMessage(),
            ]);

            throw new ServerErrorException();
        }

        logger()->info('[UserAnalysSearchService.search] returned results', ['count' => $results->count()]);

        return $results;
    }
}
