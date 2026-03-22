<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait ResolvesElasticsearchHost
{
    protected function resolveReachableHost(): string
    {
        $configuredHost = (string) config('elastic.host', 'elasticsearch');
        $port = (int) config('elastic.port', 9200);

        $candidates = array_values(array_unique([
            $configuredHost,
            '127.0.0.1',
            'localhost',
            'elasticsearch',
        ]));

        foreach ($candidates as $host) {
            if ($this->makeHttpRequest("http://{$host}:{$port}") !== null) {
                return $host;
            }
        }

        return $configuredHost;
    }

    protected function makeHttpRequest(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);

        if ($body === false) {
            return null;
        }

        return json_decode($body, true);
    }
}
