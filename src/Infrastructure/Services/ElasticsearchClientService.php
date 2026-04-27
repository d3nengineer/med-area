<?php

declare(strict_types=1);

namespace Infrastructure\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;

class ElasticsearchClientService implements ElasticsearchClientServiceContract
{
    private readonly Client $client;

    public function __construct()
    {
        $host = config('elastic.host');
        $port = config('elastic.port');

        try {
            $builder = ClientBuilder::create()->setHosts(["{$host}:{$port}"]);

            $password = config('elastic.password');
            if ($password !== null && $password !== '') {
                $builder->setBasicAuthentication('elastic', $password);
            }

            $this->client = $builder->build();
        } catch (\Throwable $e) {
            logger()->critical('[ElasticsearchClientService] connection failed', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
