<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\Audit;

use Domain\Analys\Events\UserAnalysDeleted;
use Domain\Analys\Enums\Analys;
use Domain\Audit\DTO\UserActivityAuditDTO;
use Domain\Audit\Enums\UserActivityAction;
use Domain\Audit\Enums\UserActivityEntityType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;
use Spatie\LaravelData\Optional;

class IndexUserAnalysDeletedAuditListener implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly UserActivityAuditIndexServiceContract $auditIndexService,
    ) {}

    public function handle(UserAnalysDeleted $event): void
    {
        try {
            $this->auditIndexService->index(UserActivityAuditDTO::from([
                'event_id' => (string) str()->uuid(),
                'action' => UserActivityAction::DELETED,
                'entity_type' => UserActivityEntityType::ANALYSIS,
                'entity_id' => $event->userAnalysDTO->id,
                'actor_user_id' => $event->userAnalysDTO->user_id,
                'subject_user_id' => $event->userAnalysDTO->user_id,
                'occurred_at' => now(),
                'source' => 'api',
                'metadata' => [
                    'analys_id' => $this->analysIdValue($event->userAnalysDTO->analys_id),
                ],
            ]));
        } catch (\Throwable $e) {
            logger()->error('[IndexUserAnalysDeletedAuditListener.handle] failed to index audit document', [
                'analysis_id' => $event->userAnalysDTO->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param Analys|Optional $analysId
     */
    private function analysIdValue(Analys|Optional $analysId): ?int
    {
        if ($analysId instanceof Optional) {
            return null;
        }

        return $analysId->value;
    }
}
