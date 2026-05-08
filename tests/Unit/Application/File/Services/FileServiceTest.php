<?php

declare(strict_types=1);

namespace Tests\Unit\Application\File\Services;

use Application\File\Services\FileService;
use Application\S3\DTO\Requests\CreateFilesRequestDTO;
use Application\S3\DTO\Responses\SignedFileResponseDTO;
use Application\S3\Services\Contracts\S3ServiceContract;
use Domain\File\DTO\FileDTO;
use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\Enums\FileLifecycleState;
use Domain\File\Models\File;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Shared\Enums\Storage as EnumsStorage;
use Tests\TestCase;

class FileServiceTest extends TestCase
{
    protected FileService $service;

    protected MockInterface $s3ServiceMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->s3ServiceMock = $this->mock(S3ServiceContract::class);

        $this->service = new FileService($this->s3ServiceMock);
    }

    public function test_upload_uploads_each_file_and_returns_signed_responses(): void
    {
        $user = $this->authUser();

        $firstDto = FileDTO::from([
            'user_id' => $user->id,
            'bucket' => 'bucket',
            'endpoint' => 'https://storage.test',
            'size' => 10,
            'content' => UploadedFile::fake()->image('first.jpg'),
            'key' => 'first',
        ]);

        $secondDto = FileDTO::from([
            'user_id' => $user->id,
            'bucket' => 'bucket',
            'endpoint' => 'https://storage.test',
            'size' => 12,
            'content' => UploadedFile::fake()->create('second.pdf', 12),
            'key' => 'second',
        ]);

        $request = CreateFilesRequestDTO::from([
            'files' => [$firstDto, $secondDto],
        ]);

        $firstFile = File::factory()->for($user)->make([
            'storage' => EnumsStorage::S3,
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
        ]);
        $secondFile = File::factory()->for($user)->make([
            'storage' => EnumsStorage::S3,
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
        ]);

        $firstResponse = SignedFileResponseDTO::from([
            'id' => 'file-1',
            'user_id' => $user->id,
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'download_url' => 'https://signed.test/first',
            'download_expires_at' => now()->addMinutes(5),
        ]);
        $secondResponse = SignedFileResponseDTO::from([
            'id' => 'file-2',
            'user_id' => $user->id,
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'download_url' => 'https://signed.test/second',
            'download_expires_at' => now()->addMinutes(5),
        ]);

        $this->s3ServiceMock->shouldReceive('upload')
            ->once()
            ->withArgs(function (FileDTO $file): bool {
                return $file->storage === EnumsStorage::S3 && $file->key === 'first';
            })
            ->andReturn($firstFile);
        $this->s3ServiceMock->shouldReceive('toSignedResponse')
            ->once()
            ->with($firstFile)
            ->andReturn($firstResponse);

        $this->s3ServiceMock->shouldReceive('upload')
            ->once()
            ->withArgs(function (FileDTO $file): bool {
                return $file->storage === EnumsStorage::S3 && $file->key === 'second';
            })
            ->andReturn($secondFile);
        $this->s3ServiceMock->shouldReceive('toSignedResponse')
            ->once()
            ->with($secondFile)
            ->andReturn($secondResponse);

        $result = $this->service->upload($request);

        $this->assertCount(2, $result);
        $this->assertSame('file-1', $result[0]->id);
        $this->assertSame('file-2', $result[1]->id);
    }

    public function test_index_maps_files_to_signed_responses(): void
    {
        $user = $this->getUser();
        $filters = FilterFileDTO::from([
            'user_ids' => [$user->id],
        ]);

        $file = File::factory()->for($user)->make([
            'storage' => EnumsStorage::S3,
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
        ]);

        $response = SignedFileResponseDTO::from([
            'id' => 'file-1',
            'user_id' => $user->id,
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'download_url' => 'https://signed.test/file-1',
            'download_expires_at' => now()->addMinutes(5),
        ]);

        $this->s3ServiceMock->shouldReceive('getFiles')
            ->once()
            ->with($filters)
            ->andReturn(new Collection([$file]));
        $this->s3ServiceMock->shouldReceive('toSignedResponse')
            ->once()
            ->with($file)
            ->andReturn($response);

        $result = $this->service->index($filters);

        $this->assertCount(1, $result);
        $this->assertSame('file-1', $result[0]->id);
    }

    public function test_delete_forwards_filters_to_storage_service(): void
    {
        $filters = FilterFileDTO::from([
            'ids' => [fake()->uuid()],
        ]);

        $this->s3ServiceMock->shouldReceive('delete')
            ->once()
            ->with($filters);

        $this->service->delete($filters);

        $this->assertTrue(true);
    }
}
