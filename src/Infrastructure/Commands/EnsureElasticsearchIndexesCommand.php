<?php

declare(strict_types=1);

namespace Infrastructure\Commands;

use Illuminate\Console\Command;
use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;

class EnsureElasticsearchIndexesCommand extends Command
{
    protected $signature = 'app:ensure-elasticsearch-indexes';

    protected $description = 'Ensure all application-managed Elasticsearch indexes exist.';

    public function __construct(
        private readonly AnalysSearchIndexServiceContract $analysSearchIndexService,
        private readonly UserActivityAuditIndexServiceContract $userActivityAuditIndexService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->analysSearchIndexService->ensureIndex();
        $this->userActivityAuditIndexService->ensureIndex();

        $this->info('Application-managed Elasticsearch indexes are ready.');

        return self::SUCCESS;
    }
}
