<?php

declare(strict_types=1);

namespace Application\Analys\Services;

use Application\Analys\Services\Contracts\AnalysServiceContract;
use Domain\Analys\Models\Analys;
use Illuminate\Database\Eloquent\Collection;
use Domain\Analys\Repositories\AnalysRepositoryContract;
use Shared\Exceptions\ServerErrorException;

class AnalysService implements AnalysServiceContract
{
    public function __construct(
        protected readonly AnalysRepositoryContract $analysRepository,
    ) {}

    /**
     * Get Analysis from DB
     *
     * @return Collection<array-key, Analys>
     *
     * @throws ServerErrorException
     */
    public function getAnalysis(): Collection
    {
        try {
            $result = $this->analysRepository->getMany();
        } catch (\Throwable $e) {
            logger()->error('[AnalysService.getAnalysis] failed to get analysis from DB', [
                'error' => $e->getMessage(),
            ]);

            throw new ServerErrorException();
        }

        return $result;
    }
}
