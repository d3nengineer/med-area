<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\Analys;

use Domain\Analys\Events\UserAnalysDeleted;
use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;
use Illuminate\Contracts\Queue\ShouldQueue;

class RemoveUserAnalysFromIndexListener implements ShouldQueue
{
    public function __construct(
        private readonly AnalysSearchIndexServiceContract $indexService,
    ) {}

    public function handle(UserAnalysDeleted $event): void
    {
        $id = $event->userAnalysDTO->id;

        if (! is_string($id)) {
            logger()->error('[RemoveUserAnalysFromIndexListener.handle] missing analysis id for index removal', [
                'event' => UserAnalysDeleted::class,
            ]);

            return;
        }

        try {
            $this->indexService->delete($id);

            logger()->info('[RemoveUserAnalysFromIndexListener.handle] removed document', ['id' => $id]);
        } catch (\Throwable $e) {
            logger()->error('[RemoveUserAnalysFromIndexListener.handle] failed to remove document', [
                'id'      => $id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
