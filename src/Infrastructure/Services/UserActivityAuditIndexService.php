<?php

declare(strict_types=1);

namespace Infrastructure\Services;

use Carbon\Carbon;
use Domain\Audit\DTO\UserActivityAuditDTO;
use Domain\Audit\Enums\UserActivityAction;
use Domain\Audit\Enums\UserActivityEntityType;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;
use RuntimeException;
use Spatie\LaravelData\Optional;

class UserActivityAuditIndexService implements UserActivityAuditIndexServiceContract
{
    private readonly string $indexName;

    public function __construct(
        private readonly ElasticsearchClientServiceContract $elasticsearchClientService,
    ) {
        $this->indexName = config('elastic.index_prefix') . '_user_activity_audit';
    }

    public function ensureIndex(): void
    {
        $client = $this->elasticsearchClientService->getClient();

        try {
            if ($this->resolveResponse($client->indices()->exists(['index' => $this->indexName]))->asBool()) {
                logger()->warning('[UserActivityAuditIndexService.ensureIndex] index already exists, skipping creation', [
                    'index' => $this->indexName,
                ]);

                return;
            }

            $mappingContents = file_get_contents(database_path('elasticsearch/user_activity_audit_mapping.json'));

            if ($mappingContents === false) {
                throw new RuntimeException('Failed to read Elasticsearch mapping file.');
            }

            /** @var array<string, mixed> $mapping */
            $mapping = json_decode($mappingContents, true, 512, JSON_THROW_ON_ERROR);

            $client->indices()->create([
                'index' => $this->indexName,
                'body' => $mapping,
            ]);

            logger()->info('[UserActivityAuditIndexService.ensureIndex] index created', ['index' => $this->indexName]);
        } catch (\Throwable $e) {
            logger()->error('[UserActivityAuditIndexService.ensureIndex] failed to ensure index', [
                'index' => $this->indexName,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function index(UserActivityAuditDTO $dto): void
    {
        $client = $this->elasticsearchClientService->getClient();
        $eventId = $this->requireString($dto->event_id, 'event_id');

        try {
            $client->index([
                'index' => $this->indexName,
                'id' => $eventId,
                'body' => [
                    'event_id' => $eventId,
                    'action' => $this->actionValue($dto->action),
                    'entity_type' => $this->entityTypeValue($dto->entity_type),
                    'entity_id' => $this->requireString($dto->entity_id, 'entity_id'),
                    'actor_user_id' => $this->nullableString($dto->actor_user_id),
                    'subject_user_id' => $this->nullableString($dto->subject_user_id),
                    'occurred_at' => $this->carbonValue($dto->occurred_at)?->toIso8601String(),
                    'source' => $this->requireString($dto->source, 'source'),
                    'metadata' => $dto->metadata,
                ],
            ]);

            logger()->info('[UserActivityAuditIndexService.index] document indexed', ['event_id' => $eventId]);
        } catch (\Throwable $e) {
            logger()->error('[UserActivityAuditIndexService.index] failed to index document', [
                'event_id' => $eventId,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
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

    /**
     * @param string|Optional $value
     */
    private function requireString(string|Optional $value, string $field): string
    {
        if ($value instanceof Optional) {
            throw new RuntimeException("UserActivityAuditDTO field [{$field}] is required for Elasticsearch indexing.");
        }

        return $value;
    }

    /**
     * @param string|Optional|null $value
     */
    private function nullableString(string|Optional|null $value): ?string
    {
        if ($value instanceof Optional) {
            return null;
        }

        return $value;
    }

    /**
     * @param UserActivityAction|Optional $value
     */
    private function actionValue(UserActivityAction|Optional $value): string
    {
        if ($value instanceof Optional) {
            throw new RuntimeException('UserActivityAuditDTO field [action] is required for Elasticsearch indexing.');
        }

        return $value->value;
    }

    /**
     * @param UserActivityEntityType|Optional $value
     */
    private function entityTypeValue(UserActivityEntityType|Optional $value): string
    {
        if ($value instanceof Optional) {
            throw new RuntimeException('UserActivityAuditDTO field [entity_type] is required for Elasticsearch indexing.');
        }

        return $value->value;
    }

    /**
     * @param Carbon|Optional|null $value
     */
    private function carbonValue(Carbon|Optional|null $value): ?Carbon
    {
        if ($value instanceof Optional) {
            return null;
        }

        return $value;
    }
}
