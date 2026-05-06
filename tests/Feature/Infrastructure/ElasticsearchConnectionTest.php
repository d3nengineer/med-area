<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ResolvesElasticsearchHost;
use Tests\TestCase;

#[Group('elastic')]
class ElasticsearchConnectionTest extends TestCase
{
    use ResolvesElasticsearchHost;

    private string $esBaseUrl;

    public function setUp(): void
    {
        parent::setUp();

        $host = $this->resolveReachableHost();
        $port = config('elastic.port', 9200);

        $this->esBaseUrl = "http://{$host}:{$port}";

        if ($this->makeHttpRequest($this->esBaseUrl) === null) {
            $this->markTestSkipped(
                "Live Elasticsearch is not reachable on {$host}:{$port}. Tried: {$this->describeElasticsearchConnectionAttempts()}."
            );
        }
    }

    public function test_elasticsearch_is_reachable(): void
    {
        $response = $this->makeHttpRequest($this->esBaseUrl . '/_cluster/health');

        $this->assertNotNull($response, 'Elasticsearch did not respond');
        $this->assertArrayHasKey('status', $response);
        $this->assertContains($response['status'], ['green', 'yellow'], 'Cluster is not healthy');
    }

    public function test_elasticsearch_returns_version_info(): void
    {
        $response = $this->makeHttpRequest($this->esBaseUrl);

        $this->assertNotNull($response);
        $this->assertArrayHasKey('version', $response);
        $this->assertArrayHasKey('number', $response['version']);
    }

    public function test_medarea_index_exists_after_log_write(): void
    {
        Log::channel('json')->info('ES connectivity test log entry', [
            'test' => true,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Give Logstash a moment to forward the log to ES
        sleep(3);

        $prefix = config('elastic.index_prefix', 'medarea');
        $today = now()->format('Y.m.d');
        $index = "{$prefix}-logs-{$today}";

        $response = $this->makeHttpRequest($this->esBaseUrl . "/{$index}/_count");

        $this->assertNotNull($response, "Index {$index} not found in Elasticsearch");
        $this->assertArrayHasKey('count', $response);
        $this->assertGreaterThan(0, $response['count'], "Index {$index} is empty");
    }
}
