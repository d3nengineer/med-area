<?php

declare(strict_types=1);

namespace Tests\Unit\Application\S3\Services;

use Carbon\Carbon;
use Domain\File\DTO\FileDTO;
use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\Events\FileSoftDeleted;
use Domain\File\Events\FileUploaded;
use Application\S3\Services\YCloudS3Service;
use Domain\File\Factories\FileFactory;
use Domain\File\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Domain\File\Repositories\FileRepositoryContract;
use Mockery\MockInterface;
use Shared\Enums\Storage as EnumsStorage;
use Shared\Exceptions\ServerErrorException;
use Tests\TestCase;
use Mockery;

class YCloudS3ServiceTest extends TestCase
{
    /**
     * Сontains the service being tested
     *
     * @var YCloudS3Service
     */
    protected YCloudS3Service $service;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake(EnumsStorage::S3_TESTING->value);

        $this->service = new YCloudS3Service(
            app(FileRepositoryContract::class),
            Storage::disk(EnumsStorage::S3_TESTING->value),
            EnumsStorage::S3_TESTING,
        );
    }

    public function test_upload_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $dto = $this->getFileDTO();
        $dto->user_id = $user->id;

        // Result from method of service
        $result = $this->service->upload($dto);

        // Assert that result instance of File Model
        $this->assertInstanceOf(File::class, $result);

        // Assert that File saved into yc s3
        $this->assertTrue($this->service->fileExists($result->key));

        // Assert that database has File data
        $this->assertDatabaseHas(File::class, $dto->except('content')->toArray());
    }

    public function test_upload_dispatches_file_uploaded_event_with_saved_file_snapshot(): void
    {
        Event::fake();

        $user = $this->authUser();
        $dto = $this->getFileDTO();
        $dto->user_id = $user->id;

        $result = $this->service->upload($dto);

        Event::assertDispatchedTimes(FileUploaded::class, 1);
        Event::assertDispatched(FileUploaded::class, function (FileUploaded $event) use ($result, $user): bool {
            return $event->fileDTO->id === $result->id
                && $event->fileDTO->user_id === $user->id
                && $event->fileDTO->storage === EnumsStorage::S3_TESTING
                && $event->fileDTO->key === $result->key;
        });
    }

    public function test_upload_unauth_and_user_id_is_empty(): void
    {
        // Data for testing
        $dto = $this->getFileDTO();

        // Expect server error exception
        $this->expectException(ServerErrorException::class);

        // Call method of service
        $this->service->upload($dto);

        // Assert that database missing File data
        $this->assertDatabaseMissing(File::class, $dto->except('content')->toArray());
    }

    public function test_get_files_success(): void
    {
        // Create a file record to ensure results exist
        $user = $this->getUser();
        File::factory(2)->for($user)->create();

        // Filters for testing
        $filters = FilterFileDTO::from([]);

        // Result from method of service
        $result = $this->service->getFiles($filters);

        // Check asserts
        $this->assertInstanceOf(File::class, $result->first());
    }

    public function test_get_files_success_use_filter_by_user_ids(): void
    {
        // User for testing
        $user = $this->getUser();

        // Create Files for User for testing filter
        $count = 3;
        File::factory()->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from([
            'user_ids' => [$user->id],
        ]);

        // Result from method of service
        $result = $this->service->getFiles($filters);

        // Check asserts
        $this->assertInstanceOf(File::class, $result->first());
        $this->assertCount($count, $result);
    }

    public function test_get_files_does_not_resolve_s3_disk_when_storage_access_is_not_needed(): void
    {
        $originalRegion = config('filesystems.disks.s3.region');

        try {
            config()->set('filesystems.disks.s3.region', null);
            Storage::forgetDisk(EnumsStorage::S3->value);

            $user = $this->getUser();
            File::factory()->for($user)->create();

            $service = new YCloudS3Service(
                app(FileRepositoryContract::class),
            );

            $result = $service->getFiles(FilterFileDTO::from([]));

            $this->assertInstanceOf(File::class, $result->first());
        } finally {
            config()->set('filesystems.disks.s3.region', $originalRegion);
            Storage::forgetDisk(EnumsStorage::S3->value);
        }
    }

    public function test_temporary_url_success(): void
    {
        $disk = Storage::disk(EnumsStorage::S3_TESTING->value);
        $disk->buildTemporaryUrlsUsing(function (string $path, \DateTimeInterface $expiration): string {
            return 'https://signed.test/' . $path . '?expires=' . $expiration->getTimestamp();
        });

        $expiration = now()->addMinutes(5);

        $result = $this->service->temporaryUrl('users/file.pdf', $expiration, EnumsStorage::S3_TESTING);

        $this->assertSame(
            'https://signed.test/users/file.pdf?expires=' . $expiration->getTimestamp(),
            $result,
        );
    }

    public function test_temporary_url_throws_server_error_when_disk_does_not_support_it(): void
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter&\Mockery\MockInterface $disk */
        $disk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $disk->shouldReceive('providesTemporaryUrls')->once()->andReturn(false);

        $service = new YCloudS3Service(
            app(FileRepositoryContract::class),
            $disk,
            EnumsStorage::LOCAL,
        );

        $this->expectException(ServerErrorException::class);

        $service->temporaryUrl('users/file.pdf', now()->addMinutes(5));
    }

    public function test_to_signed_response_uses_configured_ttl(): void
    {
        Carbon::setTestNow('2026-04-20 12:00:00');
        config()->set('filesystems.environments.signed_url_ttl_minutes', 9);

        $disk = Storage::disk(EnumsStorage::S3_TESTING->value);
        $disk->buildTemporaryUrlsUsing(function (string $path, \DateTimeInterface $expiration): string {
            return 'https://signed.test/' . $path . '?expires=' . $expiration->getTimestamp();
        });

        $user = $this->getUser();
        $file = File::factory()->for($user)->create([
            'storage' => EnumsStorage::S3_TESTING,
            'key' => 'users/' . $user->id . '/report.pdf',
        ]);

        $response = $this->service->toSignedResponse($file);

        $this->assertSame($file->id, $response->id);
        $this->assertSame($file->user_id, $response->user_id);
        $this->assertSame(
            'https://signed.test/users/' . $user->id . '/report.pdf?expires=' . now()->addMinutes(9)->getTimestamp(),
            $response->download_url,
        );
        $this->assertTrue($response->download_expires_at->equalTo(now()->addMinutes(9)));

        Carbon::setTestNow();
    }

    public function test_get_files_success_use_filter_by_size(): void
    {
        // User for testing
        $user = $this->getUser();

        // Create Files with size for testing filter
        $count = 3;
        File::factory(state: ['size' => 100])->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from([
            'min_size' => 99,
            'max_size' => 101,
        ]);

        // Result from method of service
        $result = $this->service->getFiles($filters);

        // Check asserts
        $this->assertInstanceOf(File::class, $result->first());
        $this->assertCount($count, $result);


        // Create Files with size for testing filter
        $count = 3;
        File::factory(state: ['size' => 100])->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from(['min_size' => 101]);

        // Result from method of service
        $result = $this->service->getFiles($filters);

        // Check asserts that not found
        $this->assertCount(0, $result);


        // Filters for testing
        $filters = FilterFileDTO::from(['max_size' => 0]);

        // Result from method of service
        $result = $this->service->getFiles($filters);

        // Check asserts that not found
        $this->assertCount(0, $result);
    }

    public function test_delete_files_success_use_filter_by_user_ids(): void
    {
        // User for testing
        $user = $this->getUser();

        // Create testing data for testing
        File::factory(3)->for($user)->create();

        // Filters for testing
        $filters = FilterFileDTO::from(['user_ids' => [$user->id]]);

        // Check asserts that not soft deleted
        $this->assertNotSoftDeleted(File::class, ['user_id' => $user->id]);

        // Call method of service
        $this->service->delete($filters);

        // Check asserts that soft deleted
        $this->assertSoftDeleted(File::class, ['user_id' => $user->id]);
    }

    public function test_delete_dispatches_file_soft_deleted_event_for_each_deleted_file(): void
    {
        Event::fake();

        $user = $this->getUser();
        $deletedFiles = File::factory(2)->for($user)->create();
        File::factory()->for($user)->create();

        $filters = FilterFileDTO::from([
            'ids' => $deletedFiles->pluck('id')->all(),
            'user_ids' => [$user->id],
        ]);

        $this->service->delete($filters);

        Event::assertDispatchedTimes(FileSoftDeleted::class, 2);

        foreach ($deletedFiles as $file) {
            Event::assertDispatched(FileSoftDeleted::class, function (FileSoftDeleted $event) use ($file): bool {
                return $event->fileDTO->id === $file->id
                    && $event->fileDTO->user_id === $file->user_id
                    && $event->fileDTO->storage === $file->storage;
            });
        }
    }

    public function test_delete_processes_files_in_batches_before_dispatching_soft_delete_events(): void
    {
        Event::fake();

        $user = $this->getUser();
        $filters = FilterFileDTO::from(['user_ids' => [$user->id]]);

        $firstBatch = new Collection([
            $this->makeDeletionBatchFile('file-1', $user->id),
            $this->makeDeletionBatchFile('file-2', $user->id),
        ]);
        $secondBatch = new Collection([
            $this->makeDeletionBatchFile('file-3', $user->id),
        ]);
        $emptyBatch = new Collection();

        /** @var FileRepositoryContract|MockInterface $repositoryMock */
        $repositoryMock = $this->mock(FileRepositoryContract::class);
        $repositoryMock->shouldReceive('getDeletionBatch')
            ->times(3)
            ->with($filters, 500)
            ->andReturn($firstBatch, $secondBatch, $emptyBatch);
        $repositoryMock->shouldReceive('deleteMany')
            ->once()
            ->with(Mockery::on(function (FilterFileDTO $batchFilters) use ($filters): bool {
                return $batchFilters->user_ids === $filters->user_ids
                    && $batchFilters->ids === ['file-1', 'file-2'];
            }))
            ->andReturn(2);
        $repositoryMock->shouldReceive('deleteMany')
            ->once()
            ->with(Mockery::on(function (FilterFileDTO $batchFilters) use ($filters): bool {
                return $batchFilters->user_ids === $filters->user_ids
                    && $batchFilters->ids === ['file-3'];
            }))
            ->andReturn(1);

        $service = new YCloudS3Service($repositoryMock);

        $service->delete($filters);

        Event::assertDispatchedTimes(FileSoftDeleted::class, 3);
    }

    public function test_delete_throws_when_batch_delete_makes_no_progress(): void
    {
        Event::fake();

        $user = $this->getUser();
        $filters = FilterFileDTO::from(['user_ids' => [$user->id]]);
        $batch = new Collection([
            $this->makeDeletionBatchFile('file-1', $user->id),
        ]);

        /** @var FileRepositoryContract|MockInterface $repositoryMock */
        $repositoryMock = $this->mock(FileRepositoryContract::class);
        $repositoryMock->shouldReceive('getDeletionBatch')
            ->once()
            ->with($filters, 500)
            ->andReturn($batch);
        $repositoryMock->shouldReceive('deleteMany')
            ->once()
            ->with(Mockery::on(function (FilterFileDTO $batchFilters) use ($filters): bool {
                return $batchFilters->user_ids === $filters->user_ids
                    && $batchFilters->ids === ['file-1'];
            }))
            ->andReturn(0);

        $service = new YCloudS3Service($repositoryMock);

        try {
            $service->delete($filters);
            $this->fail('Expected delete() to throw a server error when batch deletion makes no progress.');
        } catch (ServerErrorException) {
            Event::assertNotDispatched(FileSoftDeleted::class);
        }
    }

    public function test_delete_files_success_use_filter_by_size(): void
    {
        // User for testing
        $user = $this->getUser();

        // Create Files with size for testing filter
        $count = 2;
        $size = 100;
        $endpoint = 'test-1'; // for indenifier test into this method
        File::factory(state: ['size' => $size, 'endpoint' => $endpoint])->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from([
            'min_size' => $size - 1,
            'max_size' => $size + 1,
            'endpoint' => $endpoint,
        ]);

        // Check asserts that not soft deleted
        $this->assertNotSoftDeleted(File::class, [
            'user_id' => $user->id,
            'size' => $size,
            'endpoint' => $endpoint,
        ]);

        // Call method of service
        $this->service->delete($filters);

        // Check asserts that soft deleted
        $this->assertSoftDeleted(File::class, [
            'user_id' => $user->id,
            'size' => $size,
            'endpoint' => $endpoint,
        ]);


        // Create Files with size for testing filter
        $endpoint = 'test-2';
        File::factory(state: ['size' => $size, 'endpoint' => $endpoint])->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from([
            'min_size' => $size + 1,
            'endpoint' => $endpoint,
        ]);

        // Call method of service
        $this->service->delete($filters);

        // Check asserts that soft deleted
        $this->assertNotSoftDeleted(File::class, [
            'user_id' => $user->id,
            'size' => $size,
            'endpoint' => $endpoint,
        ]);
    }

    public function test_force_delete_files_success_use_filter_by_user_ids(): void
    {
        // User for testing
        $user = $this->getUser();

        // Create testing data with status soft deleted for testing
        File::factory(3, ['deleted_at' => now()])->for($user)->create();

        // Filters for testing
        $filters = FilterFileDTO::from(['user_ids' => [$user->id]]);

        // Check asserts that soft deleted
        $this->assertSoftDeleted(File::class, ['user_id' => $user->id]);

        // Call method of service
        $this->service->forceDelete($filters);

        // Check asserts that force deleted
        $this->assertDatabaseMissing(File::class, ['user_id' => $user->id]);
    }

    public function test_force_delete_files_success_use_filter_by_size(): void
    {
        // User for testing
        $user = $this->getUser();

        // Create Files with size for testing filter
        $count = 2;
        $size = 100;
        $endpoint = 'test-1';  // for indenifier test into this method
        File::factory(state: [
            'deleted_at' => now(),
            'size' => $size,
            'endpoint' => $endpoint,
        ])->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from([
            'min_size' => $size - 1,
            'max_size' => $size + 1,
            'endpoint' => $endpoint,
        ]);

        // Check asserts that not soft deleted
        $this->assertSoftDeleted(File::class, [
            'user_id' => $user->id,
            'size' => $size,
            'endpoint' => $endpoint,
        ]);

        // Call method of service
        $this->service->forceDelete($filters);

        // Check asserts that soft deleted
        $this->assertDatabaseMissing(File::class, [
            'user_id' => $user->id,
            'size' => 100,
            'endpoint' => 'test-1',
        ]);


        // Create Files with size for testing filter
        $endpoint = 'test-2';
        File::factory(state: [
            'deleted_at' => now(),
            'size' => $size,
            'endpoint' => $endpoint,
        ])->for($user)->createMany($count);

        // Filters for testing
        $filters = FilterFileDTO::from([
            'min_size' => $size + 1,
            'endpoint' => $endpoint,
        ]);

        // Check asserts that soft deleted
        $this->assertSoftDeleted(File::class, [
            'user_id' => $user->id,
            'size' => $size,
            'endpoint' => $endpoint,
        ]);

        // Call method of service
        $this->service->forceDelete($filters);

        // Check asserts that not force deleted
        $this->assertSoftDeleted(File::class, [
            'user_id' => $user->id,
            'size' => $size,
            'endpoint' => $endpoint,
        ]);
    }


    protected function getFileDTO(): FileDTO
    {
        $factory = new FileFactory();

        $dto = FileDTO::from($factory->definition());

        $dto->content = UploadedFile::fake()->image('testing.jpg');

        return $dto;
    }

    private function makeDeletionBatchFile(string $id, string $userId): File
    {
        $file = new File();
        $file->forceFill([
            'id' => $id,
            'user_id' => $userId,
            'storage' => EnumsStorage::S3_TESTING,
            'key' => 'users/' . $userId . '/' . $id . '.pdf',
        ]);

        return $file;
    }
}
