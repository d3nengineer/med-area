<?php

declare(strict_types=1);

namespace Infrastructure\Repositories;

use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Repositories\UserAnalysSearchRepositoryContract;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Http\Promise\Promise;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;
use Illuminate\Support\Collection;

class UserAnalysSearchRepository implements UserAnalysSearchRepositoryContract
{
    private readonly string $indexName;

    public function __construct(
        private readonly ElasticsearchClientServiceContract $elasticsearchClientService,
    ) {
        $this->indexName = config('elastic.index_prefix') . '_user_analys';
    }

    public function search(SearchUserAnalysDTO $dto): Collection
    {
        try {
            $response = $this->elasticsearchClientService->getClient()->search([
                'index' => $this->indexName,
                'body'  => [
                    'from'  => $dto->offset,
                    'size'  => $dto->limit,
                    'query' => [
                        'bool' => [
                            'must'   => [
                                [
                                    'multi_match' => [
                                        'query'  => $dto->query,
                                        'fields' => ['analys_name', 'analys_name.keyword'],
                                    ],
                                ],
                            ],
                            'filter' => [
                                ['term' => ['user_id' => $dto->userId]],
                            ],
                        ],
                    ],
                ],
            ]);

            /** @var array<int, array<string, mixed>> $hits */
            $hits = $this->resolveResponse($response)->asArray()['hits']['hits'] ?? [];

            return collect($hits)
                ->map(function (array $hit): UserAnalysDTO {
                    /** @var array<string, mixed> $source */
                    $source = is_array($hit['_source'] ?? null) ? $hit['_source'] : [];

                    return UserAnalysDTO::from([
                        'id' => (string) ($hit['_id'] ?? ''),
                        ...$source,
                    ]);
                })
                ->values();
        } catch (\Throwable $e) {
            logger()->error('[UserAnalysSearchRepository.search] search failed', [
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
}
