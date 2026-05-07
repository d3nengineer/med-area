<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Listeners\Audit;

use Carbon\Carbon;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;
use Domain\Audit\DTO\UserActivityAuditDTO;
use Domain\Audit\Enums\UserActivityAction;
use Domain\Audit\Enums\UserActivityEntityType;
use Domain\File\DTO\FileDTO;
use Domain\File\Events\FileSoftDeleted;
use Domain\File\Events\FileUploaded;
use Domain\File\Factories\FileFactory;
use Domain\Analys\Events\UserAnalysCreated;
use Domain\Analys\Events\UserAnalysDeleted;
use Domain\Analys\Factories\UserAnalysFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Infrastructure\Listeners\Audit\IndexFileSoftDeletedAuditListener;
use Infrastructure\Listeners\Audit\IndexFileUploadedAuditListener;
use Infrastructure\Listeners\Audit\IndexUserAnalysCreatedAuditListener;
use Infrastructure\Listeners\Audit\IndexUserAnalysDeletedAuditListener;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;
use Mockery;
use Shared\Enums\Storage as EnumsStorage;
use Tests\TestCase;

class UserActivityAuditListenersTest extends TestCase
{
    public function test_file_uploaded_listener_maps_safe_audit_document(): void
    {
        $fileDTO = $this->makeFileDTO();
        $listener = new IndexFileUploadedAuditListener($this->mockAuditIndexService(function (UserActivityAuditDTO $dto) use ($fileDTO): bool {
            return $dto->action === UserActivityAction::UPLOADED
                && $dto->entity_type === UserActivityEntityType::FILE
                && $dto->entity_id === $fileDTO->id
                && $dto->actor_user_id === $fileDTO->user_id
                && $dto->subject_user_id === $fileDTO->user_id
                && $dto->source === 'api'
                && $dto->metadata === ['storage' => EnumsStorage::S3_TESTING->value]
                && is_string($dto->event_id)
                && $dto->event_id !== ''
                && $dto->occurred_at instanceof Carbon;
        }));

        $listener->handle(new FileUploaded($fileDTO));
    }

    public function test_file_soft_deleted_listener_maps_safe_audit_document(): void
    {
        $fileDTO = $this->makeFileDTO();
        $listener = new IndexFileSoftDeletedAuditListener($this->mockAuditIndexService(function (UserActivityAuditDTO $dto) use ($fileDTO): bool {
            return $dto->action === UserActivityAction::SOFT_DELETED
                && $dto->entity_type === UserActivityEntityType::FILE
                && $dto->entity_id === $fileDTO->id
                && $dto->actor_user_id === $fileDTO->user_id
                && $dto->subject_user_id === $fileDTO->user_id
                && $dto->metadata === ['storage' => EnumsStorage::S3_TESTING->value]
                && ! array_key_exists('key', $dto->metadata)
                && $dto->occurred_at instanceof Carbon;
        }));

        $listener->handle(new FileSoftDeleted($fileDTO));
    }

    public function test_user_analysis_created_listener_maps_safe_audit_document(): void
    {
        $analysisDTO = $this->makeAnalysDTO();
        $listener = new IndexUserAnalysCreatedAuditListener($this->mockAuditIndexService(function (UserActivityAuditDTO $dto) use ($analysisDTO): bool {
            return $dto->action === UserActivityAction::CREATED
                && $dto->entity_type === UserActivityEntityType::ANALYSIS
                && $dto->entity_id === $analysisDTO->id
                && $dto->actor_user_id === $analysisDTO->user_id
                && $dto->subject_user_id === $analysisDTO->user_id
                && $dto->metadata === ['analys_id' => Analys::D3->value]
                && ! array_key_exists('data', $dto->metadata)
                && $dto->occurred_at instanceof Carbon;
        }));

        $listener->handle(new UserAnalysCreated($analysisDTO));
    }

    public function test_user_analysis_deleted_listener_maps_safe_audit_document(): void
    {
        $analysisDTO = $this->makeAnalysDTO();
        $listener = new IndexUserAnalysDeletedAuditListener($this->mockAuditIndexService(function (UserActivityAuditDTO $dto) use ($analysisDTO): bool {
            return $dto->action === UserActivityAction::DELETED
                && $dto->entity_type === UserActivityEntityType::ANALYSIS
                && $dto->entity_id === $analysisDTO->id
                && $dto->actor_user_id === $analysisDTO->user_id
                && $dto->subject_user_id === $analysisDTO->user_id
                && $dto->metadata === ['analys_id' => Analys::D3->value]
                && ! array_key_exists('data', $dto->metadata)
                && $dto->occurred_at instanceof Carbon;
        }));

        $listener->handle(new UserAnalysDeleted($analysisDTO));
    }

    public function test_audit_listeners_are_queued_after_commit(): void
    {
        $listenerClasses = [
            IndexFileUploadedAuditListener::class,
            IndexFileSoftDeletedAuditListener::class,
            IndexUserAnalysCreatedAuditListener::class,
            IndexUserAnalysDeletedAuditListener::class,
        ];

        foreach ($listenerClasses as $listenerClass) {
            $this->assertContains(ShouldQueue::class, class_implements($listenerClass));

            $listener = new $listenerClass(
                Mockery::mock(UserActivityAuditIndexServiceContract::class),
            );

            $this->assertTrue($listener->afterCommit);
        }
    }

    private function mockAuditIndexService(callable $assertion): UserActivityAuditIndexServiceContract
    {
        $service = Mockery::mock(UserActivityAuditIndexServiceContract::class);
        $service->shouldReceive('index')
            ->once()
            ->with(Mockery::on($assertion));

        return $service;
    }

    private function makeFileDTO(): FileDTO
    {
        $user = $this->getUser();
        $definition = new FileFactory()->definition();

        return FileDTO::from(array_merge($definition, [
            'id' => fake()->uuid(),
            'user_id' => $user->id,
            'storage' => EnumsStorage::S3_TESTING->value,
            'created_at' => now(),
        ]));
    }

    private function makeAnalysDTO(): UserAnalysDTO
    {
        $user = $this->getUser();
        $definition = new UserAnalysFactory()->definition();

        return UserAnalysDTO::from(array_merge($definition, [
            'id' => fake()->uuid(),
            'user_id' => $user->id,
            'analys_id' => Analys::D3->value,
            'analys_name' => Analys::D3->name,
            'data' => 4.8,
            'created_at' => now(),
        ]));
    }
}
