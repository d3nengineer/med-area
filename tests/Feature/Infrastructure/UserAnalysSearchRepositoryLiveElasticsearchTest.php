<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Enums\Unit;
use Domain\Analys\Repositories\UserAnalysSearchRepositoryContract;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;
use Elastic\Elasticsearch\Client;
use Http\Promise\Promise;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ResolvesElasticsearchHost;
use Tests\TestCase;

#[Group('elastic')]
class UserAnalysSearchRepositoryLiveElasticsearchTest extends TestCase
{
    use ResolvesElasticsearchHost;

    private string $indexName;

    private string $elasticsearchHost;

    private Client $client;

    private UserAnalysSearchRepositoryContract $repository;

    private AnalysSearchIndexServiceContract $indexService;

    public function setUp(): void
    {
        parent::setUp();

        $this->elasticsearchHost = $this->resolveReachableHost();

        config()->set('elastic.host', $this->elasticsearchHost);
        config()->set('elastic.port', 9200);
        config()->set('elastic.index_prefix', 'medarea_test_' . str()->lower((string) str()->uuid()));

        $this->indexName = config('elastic.index_prefix') . '_user_analys';
        $this->client = app(ElasticsearchClientServiceContract::class)->getClient();
        $this->repository = app(UserAnalysSearchRepositoryContract::class);
        $this->indexService = app(AnalysSearchIndexServiceContract::class);

        if (! $this->isElasticsearchReachable()) {
            $this->markTestSkipped(
                "Live Elasticsearch is not reachable on {$this->elasticsearchHost}:9200. Tried: {$this->describeElasticsearchConnectionAttempts()}."
            );
        }

        $this->deleteIndexIfExists();
        $this->indexService->ensureIndex();
    }

    public function tearDown(): void
    {
        $this->deleteIndexIfExists();

        parent::tearDown();
    }

    public function test_repository_search_returns_only_matching_documents_from_live_elasticsearch(): void
    {
        $user = $this->getUser();
        $otherUser = $this->getUser();

        $matchingDto = UserAnalysDTO::from([
            'id' => 'analysis-d3-own-user',
            'user_id' => $user->id,
            'analys_id' => Analys::D3,
            'analys_name' => Analys::D3->name,
            'data' => 5.5,
            'unit' => Unit::GL,
            'created_at' => now(),
        ]);

        $sameQueryDifferentUserDto = UserAnalysDTO::from([
            'id' => 'analysis-d3-other-user',
            'user_id' => $otherUser->id,
            'analys_id' => Analys::D3,
            'analys_name' => Analys::D3->name,
            'data' => 6.1,
            'unit' => Unit::GL,
            'created_at' => now(),
        ]);

        $differentQuerySameUserDto = UserAnalysDTO::from([
            'id' => 'analysis-b12-own-user',
            'user_id' => $user->id,
            'analys_id' => Analys::B12,
            'analys_name' => Analys::B12->name,
            'data' => 12.4,
            'unit' => Unit::PERCENT,
            'created_at' => now(),
        ]);

        $this->indexService->index($matchingDto);
        $this->indexService->index($sameQueryDifferentUserDto);
        $this->indexService->index($differentQuerySameUserDto);
        $this->refreshIndex();

        $result = $this->repository->search(
            new SearchUserAnalysDTO(query: Analys::D3->name, userId: $user->id),
        );
        /** @var UserAnalysDTO $firstResult */
        $firstResult = $result->first();

        $this->assertCount(1, $result);
        $this->assertSame($matchingDto->id, $firstResult->id);
        $this->assertSame($user->id, $firstResult->user_id);
        $this->assertSame(Analys::D3->name, $firstResult->analys_name);
    }

    public function test_repository_search_returns_empty_collection_when_live_elasticsearch_has_no_hits(): void
    {
        $user = $this->getUser();

        $this->indexService->index(UserAnalysDTO::from([
            'id' => 'analysis-b9-own-user',
            'user_id' => $user->id,
            'analys_id' => Analys::B9,
            'analys_name' => Analys::B9->name,
            'data' => 8.4,
            'unit' => Unit::GL,
            'created_at' => now(),
        ]));
        $this->refreshIndex();

        $result = $this->repository->search(
            new SearchUserAnalysDTO(query: 'NonExistentAnalysis', userId: $user->id),
        );

        $this->assertCount(0, $result);
    }

    private function refreshIndex(): void
    {
        $this->client->indices()->refresh(['index' => $this->indexName]);
    }

    private function deleteIndexIfExists(): void
    {
        if (! isset($this->client)) {
            return;
        }

        try {
            $response = $this->client->indices()->exists(['index' => $this->indexName]);
        } catch (\Throwable $e) {
            logger()->warning('[UserAnalysSearchRepositoryLiveElasticsearchTest] Skipping index cleanup', [
                'index' => $this->indexName,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if ($this->resolveResponse($response)->asBool()) {
            $this->client->indices()->delete(['index' => $this->indexName]);
        }
    }

    private function isElasticsearchReachable(): bool
    {
        try {
            $info = $this->resolveResponse($this->client->info())->asArray();
        } catch (\Throwable $e) {
            logger()->warning('[UserAnalysSearchRepositoryLiveElasticsearchTest] Elasticsearch unavailable', [
                'host' => $this->elasticsearchHost,
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        return array_key_exists('version', $info);
    }

    /**
     * @param Elasticsearch|Promise $response
     */
    private function resolveResponse(Elasticsearch|Promise $response): Elasticsearch
    {
        if ($response instanceof Promise) {
            /** @var Elasticsearch $resolved */
            $resolved = $response->wait();

            return $resolved;
        }

        return $response;
    }
}
