<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Carbon\Carbon;
use Domain\Audit\DTO\UserActivityAuditDTO;
use Domain\Audit\Enums\UserActivityAction;
use Domain\Audit\Enums\UserActivityEntityType;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ResolvesElasticsearchHost;
use Tests\TestCase;

#[Group('elastic')]
class UserActivityAuditIndexServiceLiveElasticsearchTest extends TestCase
{
    use ResolvesElasticsearchHost;

    private string $indexName;

    private string $elasticsearchHost;

    private Client $client;

    private UserActivityAuditIndexServiceContract $indexService;

    public function setUp(): void
    {
        parent::setUp();

        $this->elasticsearchHost = $this->resolveReachableHost();

        config()->set('elastic.host', $this->elasticsearchHost);
        config()->set('elastic.port', 9200);
        config()->set('elastic.index_prefix', 'medarea_test_' . str()->lower((string) str()->uuid()));

        $this->indexName = config('elastic.index_prefix') . '_user_activity_audit';
        $this->client = app(ElasticsearchClientServiceContract::class)->getClient();
        $this->indexService = app(UserActivityAuditIndexServiceContract::class);

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

    public function test_audit_index_stores_representative_file_and_analysis_documents(): void
    {
        Carbon::setTestNow('2026-05-06 12:00:00');

        $fileAudit = UserActivityAuditDTO::from([
            'event_id' => 'audit-file-uploaded-1',
            'action' => UserActivityAction::UPLOADED,
            'entity_type' => UserActivityEntityType::FILE,
            'entity_id' => 'file-1',
            'actor_user_id' => 'user-1',
            'subject_user_id' => 'user-1',
            'occurred_at' => now(),
            'source' => 'api',
            'metadata' => ['storage' => 's3'],
        ]);

        $analysisAudit = UserActivityAuditDTO::from([
            'event_id' => 'audit-analysis-deleted-1',
            'action' => UserActivityAction::DELETED,
            'entity_type' => UserActivityEntityType::ANALYSIS,
            'entity_id' => 'analysis-1',
            'actor_user_id' => 'user-1',
            'subject_user_id' => 'user-1',
            'occurred_at' => now()->addMinute(),
            'source' => 'api',
            'metadata' => ['analys_id' => 3],
        ]);

        $this->indexService->index($fileAudit);
        $this->indexService->index($analysisAudit);
        $this->refreshIndex();

        $fileDocument = $this->resolveResponse($this->client->get([
            'index' => $this->indexName,
            'id' => 'audit-file-uploaded-1',
        ]))->asArray();

        $this->assertSame('uploaded', $fileDocument['_source']['action']);
        $this->assertSame('file', $fileDocument['_source']['entity_type']);
        $this->assertSame(['storage' => 's3'], $fileDocument['_source']['metadata']);

        $searchResult = $this->resolveResponse($this->client->search([
            'index' => $this->indexName,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['term' => ['entity_type' => 'analysis']],
                            ['term' => ['action' => 'deleted']],
                        ],
                    ],
                ],
            ],
        ]))->asArray();

        $this->assertSame(1, $searchResult['hits']['total']['value']);
        $this->assertSame('analysis-1', $searchResult['hits']['hits'][0]['_source']['entity_id']);
        $this->assertSame(['analys_id' => 3], $searchResult['hits']['hits'][0]['_source']['metadata']);

        Carbon::setTestNow();
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
            logger()->warning('[UserActivityAuditIndexServiceLiveElasticsearchTest] Skipping index cleanup', [
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
            logger()->warning('[UserActivityAuditIndexServiceLiveElasticsearchTest] Elasticsearch unavailable', [
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
