<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure;

use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;
use Mockery;
use Tests\TestCase;

class EnsureElasticsearchIndexesCommandTest extends TestCase
{
    public function test_command_ensures_both_application_managed_elasticsearch_indexes(): void
    {
        $analysisIndexService = Mockery::mock(AnalysSearchIndexServiceContract::class);
        $analysisIndexService->shouldReceive('ensureIndex')->once();

        $auditIndexService = Mockery::mock(UserActivityAuditIndexServiceContract::class);
        $auditIndexService->shouldReceive('ensureIndex')->once();

        $this->app->instance(AnalysSearchIndexServiceContract::class, $analysisIndexService);
        $this->app->instance(UserActivityAuditIndexServiceContract::class, $auditIndexService);

        $this->artisan('app:ensure-elasticsearch-indexes')
            ->assertSuccessful();
    }
}
