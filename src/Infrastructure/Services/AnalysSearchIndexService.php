<?php

declare(strict_types=1);

namespace Infrastructure\Services;

use Carbon\Carbon;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Enums\Unit;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;
use RuntimeException;
use Spatie\LaravelData\Optional;

class AnalysSearchIndexService implements AnalysSearchIndexServiceContract
{
    private readonly string $indexName;

    public function __construct(
        private readonly ElasticsearchClientServiceContract $elasticsearchClientService,
    ) {
        $this->indexName = config('elastic.index_prefix') . '_user_analys';
    }

    public function ensureIndex(): void
    {
        $client = $this->elasticsearchClientService->getClient();

        if ($this->resolveResponse($client->indices()->exists(['index' => $this->indexName]))->asBool()) {
            logger()->warning('[AnalysSearchIndexService.ensureIndex] index already exists, skipping creation', [
                'index' => $this->indexName,
            ]);

            return;
        }

        $mappingContents = file_get_contents(database_path('elasticsearch/user_analys_mapping.json'));

        if ($mappingContents === false) {
            throw new RuntimeException('Failed to read Elasticsearch mapping file.');
        }

        /** @var array<string, mixed> $mapping */
        $mapping = json_decode($mappingContents, true, 512, JSON_THROW_ON_ERROR);

        $client->indices()->create([
            'index' => $this->indexName,
            'body'  => $mapping,
        ]);

        logger()->info('[AnalysSearchIndexService.ensureIndex] index created', ['index' => $this->indexName]);
    }

    public function index(UserAnalysDTO $dto): void
    {
        $client = $this->elasticsearchClientService->getClient();

        $client->index([
            'index' => $this->indexName,
            'id'    => $this->requireString($dto->id, 'id'),
            'body'  => [
                'user_id'     => $this->requireString($dto->user_id, 'user_id'),
                'analys_id'   => $this->analysIdValue($dto->analys_id),
                'analys_name' => $this->requireString($dto->analys_name, 'analys_name'),
                'data'        => $this->floatValue($dto->data),
                'unit'        => $this->unitValue($dto->unit),
                'created_at'  => $this->carbonValue($dto->created_at)?->toIso8601String(),
            ],
        ]);

        logger()->info('[AnalysSearchIndexService.index] document indexed', ['id' => $this->requireString($dto->id, 'id')]);
    }

    public function delete(string $id): void
    {
        $client = $this->elasticsearchClientService->getClient();

        $client->delete([
            'index' => $this->indexName,
            'id'    => $id,
        ]);

        logger()->info('[AnalysSearchIndexService.delete] document removed', ['id' => $id]);
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
            throw new RuntimeException("UserAnalysDTO field [{$field}] is required for Elasticsearch indexing.");
        }

        return $value;
    }

    /**
     * @param float|Optional $value
     */
    private function floatValue(float|Optional $value): ?float
    {
        return $value instanceof Optional ? null : $value;
    }

    /**
     * @param Analys|Optional $value
     */
    private function analysIdValue(Analys|Optional $value): ?int
    {
        return $value instanceof Optional ? null : $value->value;
    }

    /**
     * @param Unit|Optional $value
     */
    private function unitValue(Unit|Optional $value): ?string
    {
        return $value instanceof Optional ? null : $value->value;
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
