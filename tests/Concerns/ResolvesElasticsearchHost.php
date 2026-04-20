<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait ResolvesElasticsearchHost
{
    /** @var list<string> */
    protected array $elasticsearchConnectionAttempts = [];

    protected function resolveReachableHost(): string
    {
        $configuredHost = (string) config('elastic.host', 'elasticsearch');
        $port = (int) config('elastic.port', 9200);
        $this->elasticsearchConnectionAttempts = [];

        $candidates = array_values(array_unique([
            $configuredHost,
            '127.0.0.1',
            'localhost',
            'elasticsearch',
        ]));

        foreach ($candidates as $host) {
            $url = "http://{$host}:{$port}";
            $this->elasticsearchConnectionAttempts[] = $url;

            if ($this->makeHttpRequest($url) !== null) {
                return $host;
            }
        }

        return $configuredHost;
    }

    protected function describeElasticsearchConnectionAttempts(): string
    {
        if ($this->elasticsearchConnectionAttempts === []) {
            return 'no endpoints attempted';
        }

        return implode(', ', $this->elasticsearchConnectionAttempts);
    }

    /**
     * @return array<string, mixed>|null
     */
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

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
