<?php

declare(strict_types=1);

namespace Infrastructure\Services\Contracts;

use Elastic\Elasticsearch\Client;

interface ElasticsearchClientServiceContract
{
    public function getClient(): Client;
}
