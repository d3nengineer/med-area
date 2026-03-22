<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\Analys;

use Domain\Analys\Events\UserAnalysCreated;
use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;

class IndexUserAnalysListener
{
    public function __construct(
        private readonly AnalysSearchIndexServiceContract $indexService,
    ) {}

    public function handle(UserAnalysCreated $event): void
    {
        $id = $event->userAnalysDTO->id;

        try {
            $this->indexService->index($event->userAnalysDTO);
            logger()->info('[IndexUserAnalysListener.handle] indexed document', ['id' => $id]);
        } catch (\Throwable $e) {
            logger()->error('[IndexUserAnalysListener.handle] failed to index document', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
