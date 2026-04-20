<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Analys\Services;

use Application\Analys\Services\UserAnalysSearchService;
use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Repositories\UserAnalysSearchRepositoryContract;
use Illuminate\Support\Collection;
use Mockery\Expectation;
use Mockery\MockInterface;
use Shared\Exceptions\ServerErrorException;
use Tests\TestCase;

class UserAnalysSearchServiceTest extends TestCase
{
    protected UserAnalysSearchService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = new UserAnalysSearchService(
            app(UserAnalysSearchRepositoryContract::class),
        );
    }

    public function test_search_delegates_to_repository(): void
    {
        $user = $this->getUser();

        $dto = new SearchUserAnalysDTO(query: Analys::D3->name, userId: $user->id);

        $mockResults = collect([
            UserAnalysDTO::from(['id' => Analys::D3->value, 'analys_name' => strtolower(Analys::D3->name), 'user_id' => $user->id]),
        ]);

        /** @var UserAnalysSearchRepositoryContract|MockInterface $mock */
        $mock = $this->mock(UserAnalysSearchRepositoryContract::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('search');
        $expectation->once()
            ->with($dto)
            ->andReturn($mockResults);

        $this->service = new UserAnalysSearchService($mock);

        $result = $this->service->search($dto);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(1, $result);
    }

    public function test_search_returns_empty_collection_when_no_hits(): void
    {
        $user = $this->getUser();

        $dto = new SearchUserAnalysDTO(query: 'NonExistent', userId: $user->id);

        /** @var UserAnalysSearchRepositoryContract|MockInterface $mock */
        $mock = $this->mock(UserAnalysSearchRepositoryContract::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('search');
        $expectation->once()->andReturn(collect());

        $this->service = new UserAnalysSearchService($mock);

        $result = $this->service->search($dto);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    public function test_search_throws_server_error_exception_when_repository_fails(): void
    {
        $user = $this->getUser();

        $dto = new SearchUserAnalysDTO(query: Analys::D3->name, userId: $user->id);

        /** @var UserAnalysSearchRepositoryContract|MockInterface $mock */
        $mock = $this->mock(UserAnalysSearchRepositoryContract::class);
        /** @var Expectation $expectation */
        $expectation = $mock->shouldReceive('search');
        $expectation->once()
            ->with($dto)
            ->andThrow(new \RuntimeException('Elasticsearch unavailable'));

        $this->service = new UserAnalysSearchService($mock);

        $this->expectException(ServerErrorException::class);

        $this->service->search($dto);
    }
}
