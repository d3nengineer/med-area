<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\Audit;

use Domain\Audit\DTO\UserActivityAuditDTO;
use Domain\Audit\Enums\UserActivityAction;
use Domain\Audit\Enums\UserActivityEntityType;
use Domain\File\Events\FileUploaded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;

class IndexFileUploadedAuditListener implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly UserActivityAuditIndexServiceContract $auditIndexService,
    ) {}

    public function handle(FileUploaded $event): void
    {
        try {
            $this->auditIndexService->index(UserActivityAuditDTO::from([
                'event_id' => (string) str()->uuid(),
                'action' => UserActivityAction::UPLOADED,
                'entity_type' => UserActivityEntityType::FILE,
                'entity_id' => $event->fileDTO->id,
                'actor_user_id' => $event->fileDTO->user_id,
                'subject_user_id' => $event->fileDTO->user_id,
                'occurred_at' => $event->fileDTO->created_at ?? now(),
                'source' => 'api',
                'metadata' => [
                    'storage' => $event->fileDTO->storage->value,
                ],
            ]));
        } catch (\Throwable $e) {
            logger()->error('[IndexFileUploadedAuditListener.handle] failed to index audit document', [
                'file_id' => $event->fileDTO->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
