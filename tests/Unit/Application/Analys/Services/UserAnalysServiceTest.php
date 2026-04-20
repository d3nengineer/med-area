<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Analys\Services;

use Domain\Analys\DTO\Filters\FilterUserAnalysDTO;
use Application\Analys\DTO\Requests\CreateUserAnalysisRequestDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Application\Analys\Services\UserAnalysService;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Factories\UserAnalysFactory;
use Domain\Analys\Models\UserAnalys;
use Shared\Exceptions\ServerErrorException;
use Tests\TestCase;

class UserAnalysServiceTest extends TestCase
{
    /**
     * Сontains the service being tested
     *
     * @var UserAnalysService
     */
    protected UserAnalysService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserAnalysService::class);
    }

    public function test_create_user_analysis_success(): void
    {
        // User for testing
        $user = $this->getUser();

        // Data for testing
        $factory = new UserAnalysFactory();
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id])),
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id])),
            ],
        ]);

        // Result from method of service
        $result = $this->service->createUserAnalysis($dto);

        // Check assert result
        $this->assertIsArray($result);
        $this->assertInstanceOf(UserAnalysDTO::class, $result[0]);
        $this->assertCount(count($dto->analysis), $result);

    }

    public function test_create_user_analysis_missing_values(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $factory = new UserAnalysFactory();
        $userAnalys = UserAnalysDTO::from($factory->definition());
        $userAnalys->user_id = $user->id;

        // Data for testing - missing user_id
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [
                $userAnalys,
                $userAnalys->except('user_id'),
            ],
        ]);

        // Сheck that was exception
        $this->expectException(ServerErrorException::class);

        // Call method of service
        $this->service->createUserAnalysis($dto);
    }

    public function test_get_user_analysis_filter_by_user_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Init dto for models
        $factory = new UserAnalysFactory();
        $dto = UserAnalysDTO::from($factory->definition());

        // Init model for searching
        $dto->user_id = $user->id;
        UserAnalys::query()->create($dto->toArray());

        // Filters
        $filters = FilterUserAnalysDTO::from(['user_ids' => [$user->id]]);

        // Result from method of service
        $result = $this->service->getUserAnalysis($filters);

        // Check assert result
        $this->assertCount(1, $result);
        $this->assertInstanceOf(UserAnalys::class, $result[0]);
        $this->assertSame($user->id, $result[0]->user_id);
    }

    public function test_get_user_analysis_filter_by_analys_ids(): void
    {
        // Create records with matching analys IDs
        $user = $this->getUser();
        $factory = new UserAnalysFactory();

        $dto1 = UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id, 'analys_id' => Analys::B12->value, 'analys_name' => Analys::B12->name]));
        $dto2 = UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id, 'analys_id' => Analys::B6->value, 'analys_name' => Analys::B6->name]));
        UserAnalys::query()->create($dto1->toArray());
        UserAnalys::query()->create($dto2->toArray());

        // Filters
        $filters = FilterUserAnalysDTO::from(['analys_ids' => [Analys::B12, Analys::B6]]);

        // Get count elements after filtering
        $count = UserAnalys::query()->whereAnalysId($filters->analys_ids)->get('id')->count();

        // Result from method of service
        $result = $this->service->getUserAnalysis($filters);

        // Check assert result
        $this->assertCount($count, $result);
        $this->assertInstanceOf(UserAnalys::class, $result[0]);
    }

    public function test_get_user_analysis_filter_not_found(): void
    {
        // Filters
        $filters = FilterUserAnalysDTO::from([
            'user_ids' => [fake()->uuid()],
            // ...
        ]);

        // Result from method of service
        $result = $this->service->getUserAnalysis($filters);

        // Check assert result
        $this->assertCount(0, $result);
    }

    public function test_delete_user_analysis_filter_user_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Init dto for models
        $factory = new UserAnalysFactory();
        $dto = UserAnalysDTO::from($factory->definition());

        // Init model for deleting
        $dto->user_id = $user->id;
        $userAnalysModel = UserAnalys::query()->create($dto->toArray());

        // Filters
        $filters = FilterUserAnalysDTO::from(['user_ids' => [$user->id]]);

        // Check assert is model exists
        $this->assertModelExists($userAnalysModel);

        // Call method of service
        $this->service->deleteUserAnalysis($filters);

        // Check assert is model deleted
        $this->assertModelMissing($userAnalysModel);
    }

    public function test_delete_user_analysis_filter_analys_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Init dto for models
        $factory = new UserAnalysFactory();
        $dto = UserAnalysDTO::from($factory->definition());

        // Init model for deleting
        $dto->user_id = $user->id;
        $userAnalysModel = UserAnalys::query()->create($dto->toArray());

        // Filters
        $filters = FilterUserAnalysDTO::from(['analys_ids' => [$dto->analys_id]]);

        // Check assert is model exists
        $this->assertModelExists($userAnalysModel);

        // Call method of service
        $this->service->deleteUserAnalysis($filters);

        // Check assert is model deleted
        $this->assertModelMissing($userAnalysModel);
    }

    public function test_delete_user_analysis_filters_empty(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Init dto for models
        $factory = new UserAnalysFactory();
        $dto = UserAnalysDTO::from($factory->definition());

        // Init model for deleting
        $dto->user_id = $user->id;
        $userAnalysModel = UserAnalys::query()->create($dto->toArray());

        // Filters (empty)
        $filters = new FilterUserAnalysDTO();

        // Check expect exception
        $this->expectException(ServerErrorException::class);

        // Check assert is model exists
        $this->assertModelExists($userAnalysModel);

        // Call method of service
        $this->service->deleteUserAnalysis($filters);

        // Check assert is model deleted
        $this->assertModelMissing($userAnalysModel);
    }
}
