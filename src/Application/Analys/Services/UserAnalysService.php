<?php

declare(strict_types=1);

namespace Application\Analys\Services;

use Domain\Analys\DTO\Filters\FilterUserAnalysDTO;
use Application\Analys\DTO\Requests\CreateUserAnalysisRequestDTO;
use Application\Analys\Mappers\UserAnalysMapper;
use Domain\Analys\DTO\UserAnalysDTO;
use Application\Analys\Services\Contracts\UserAnalysServiceContract;
use Domain\Analys\Events\UserAnalysCreated;
use Domain\Analys\Events\UserAnalysDeleted;
use Domain\Analys\Models\UserAnalys;
use Domain\Analys\Repositories\UserAnalysRepositoryContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Shared\Exceptions\ServerErrorException;

class UserAnalysService implements UserAnalysServiceContract
{
    public function __construct(
        protected readonly UserAnalysRepositoryContract $userAnalysRepository,
        protected readonly UserAnalysMapper $userAnalysMapper,
    ) {}

    /**
     * Add new analysis for users
     *
     * @param CreateUserAnalysisRequestDTO $dto
     * @return array<UserAnalysDTO>
     *
     * @throws ServerErrorException
     */
    public function createUserAnalysis(CreateUserAnalysisRequestDTO $dto): array
    {
        logger()->debug('[UserAnalysService.createUserAnalysis] starting', ['analysis_count' => count($dto->analysis)]);

        DB::beginTransaction();

        try {
            $createdRecords = [];

            foreach ($dto->analysis as $userAnalys) {
                $userAnalys = $this->userAnalysMapper->assignAnalysNameFromEnum($userAnalys);

                $createdDTO = UserAnalysDTO::from(
                    $this->userAnalysRepository->create($userAnalys)
                );

                $createdRecords[] = $createdDTO;

                logger()->debug('[UserAnalysService] firing UserAnalysCreated', ['id' => $createdDTO->id]);
                event(new UserAnalysCreated($createdDTO));
            }

            DB::commit();

            return $createdRecords;

        } catch (\Throwable $e) {
            DB::rollback();

            logger()->error('[UserAnalysService.createUserAnalysis] failed to save user analys to DB', [
                'error' => $e->getMessage(),
            ]);

            throw new ServerErrorException();
        }
    }

    /**
     * Get UserAnalys Models by filters
     *
     * @param FilterUserAnalysDTO $filters
     * @return Collection<array-key, UserAnalys>
     *
     * @throws ServerErrorException
     */
    public function getUserAnalysis(FilterUserAnalysDTO $filters): Collection
    {
        logger()->debug('[UserAnalysService.getUserAnalysis] starting', ['filters' => $filters->toArray()]);

        try {
            $result = $this->userAnalysRepository->getMany($filters);
        } catch (\Throwable $e) {
            logger()->error('[UserAnalysService.getUserAnalysis] failed to get user analysis from DB', [
                'error'   => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);

            throw new ServerErrorException();
        }

        logger()->debug('[UserAnalysService.getUserAnalysis] returning records', ['count' => $result->count()]);

        return $result;
    }

    /**
     * Delete UserAnalys Models by filters
     *
     * @param FilterUserAnalysDTO $filters
     * @return void
     *
     * @throws ServerErrorException
     */
    public function deleteUserAnalysis(FilterUserAnalysDTO $filters): void
    {
        logger()->info('[UserAnalysService.deleteUserAnalysis] deleting user analysis', ['filters' => $filters->toArray()]);

        try {
            $records = $this->userAnalysRepository->getMany($filters);

            $this->userAnalysRepository->deleteMany($filters);

            foreach ($records as $record) {
                $dto = UserAnalysDTO::from($record);
                logger()->debug('[UserAnalysService] firing UserAnalysDeleted', ['id' => $dto->id]);
                event(new UserAnalysDeleted($dto));
            }
        } catch (\Throwable $e) {
            logger()->error('[UserAnalysService.deleteUserAnalysis] failed to delete user analysis from DB', [
                'error'   => $e->getMessage(),
                'context' => $filters->toArray(),
            ]);

            throw new ServerErrorException();
        }
    }
}
