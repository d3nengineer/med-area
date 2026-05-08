<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation\File\Controllers;

use Domain\File\DTO\Filters\FilterFileDTO;
use Domain\File\Enums\FileLifecycleState;
use Domain\File\Events\FileSoftDeleted;
use Domain\File\Events\FileUploaded;
use Domain\File\Factories\FileFactory;
use Domain\File\Models\File as FileModel;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Shared\Enums\Storage as EnumsStorage;
use Tests\TestCase;
use Illuminate\Http\Testing\File;

class FileControllerTest extends TestCase
{
    protected Filesystem $disk;

    /** @var array<File> */
    protected array $files;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake(EnumsStorage::S3->value);
        Storage::fake(EnumsStorage::S3_TESTING->value);

        $this->disk = Storage::disk(EnumsStorage::S3_TESTING->value);
        $this->disk->buildTemporaryUrlsUsing(function (string $path, \DateTimeInterface $expiration): string {
            return 'https://signed.test/' . $path . '?expires=' . $expiration->getTimestamp();
        });
        Storage::disk(EnumsStorage::S3->value)->buildTemporaryUrlsUsing(function (string $path, \DateTimeInterface $expiration): string {
            return 'https://signed.test/' . $path . '?expires=' . $expiration->getTimestamp();
        });

        $this->files = [
            UploadedFile::fake()->create('test1.jpg', 1024),
            UploadedFile::fake()->create('test2.pdf', 1024),
        ];
    }

    public function test_upload_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Send API Request
        $response = $this->post(route('api.files.upload'), [
            'user_id' => $user->id,
            'files' => $this->files,
        ]);

        // Check asserts
        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'lifecycle_state',
                    'download_url',
                    'download_expires_at',
                ],
            ],
        ]);
        $response->assertJsonMissingPath('data.0.path');

        /** @var array<int, array<string, mixed>> $payload */
        $payload = $response->json('data');

        $this->assertCount(2, $payload);
        $this->assertSame(FileLifecycleState::AVAILABLE->value, $payload[0]['lifecycle_state']);
        $this->assertStringStartsWith('https://signed.test/users/' . $user->id . '/', $payload[0]['download_url']);
        $this->assertNotEmpty($payload[0]['download_expires_at']);
    }

    public function test_upload_dispatches_auditable_event_per_uploaded_file_without_changing_response_shape(): void
    {
        Event::fake();

        $user = $this->authUser();

        $response = $this->post(route('api.files.upload'), [
            'user_id' => $user->id,
            'files' => $this->files,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'lifecycle_state',
                    'download_url',
                    'download_expires_at',
                ],
            ],
        ]);

        Event::assertDispatchedTimes(FileUploaded::class, count($this->files));
    }

    public function test_upload_validation_big_size(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Send API Request
        $response = $this->post(route('api.files.upload'), [
            'user_id' => $user->id,
            'files' => [
                UploadedFile::fake()->create('bigimage.jpg', 15001), // Limit: 15000 / 150001 kb
            ],
        ]);

        // Check asserts
        $response->assertUnprocessable();
        $response->assertInvalid(['files.0']);
    }

    public function test_upload_unauth(): void
    {
        // User for testing
        $user = $this->getUser();

        // Send API Request
        $response = $this->post(route('api.files.upload'), [
            'user_id' => $user->id,
            'files' => $this->files,
        ]);

        // Check assert that unauthorized
        $response->assertUnauthorized();
    }

    public function test_upload_unreal_user_id(): void
    {
        // Auth user for testing
        $this->authUser();

        // User with another user_id for testing
        $user2 = $this->getUser();

        // Send API Request
        $response = $this->post(route('api.files.upload'), [
            'user_id' => $user2->id, // another user_id
            'files' => $this->files,
        ]);

        // Check asserts
        $response->assertUnprocessable();
        $response->assertInvalid(['user_id']);
        $response->assertJsonValidationErrors([
            'user_id' => ['The user id must match the authenticated user ID.'],
        ]);
    }

    public function test_upload_validation_empty_values(): void
    {
        // Auth user for testing
        $this->authUser();

        // Send API Request
        $response = $this->post(route('api.files.upload'), [
            'user_id' => '', // empty user_id
            'files' => [], // empty files
        ]);

        // Check asserts that 422 and files is empty
        $response->assertUnprocessable();
        $response->assertInvalid(['user_id', 'files']);
    }

    public function test_index_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $count = 3;
        FileModel::factory($count)->for($user)->create();

        // Filters for testing
        $filters = FilterFileDTO::from(['user_ids' => [$user->id]]);

        // Send API Request
        $response = $this->get(route('api.files.index', $filters->toArray()));

        // Check asserts that response success
        $response->assertOk();
        $response->assertJsonCount($count, 'data');
        $response->assertJsonMissingPath('data.0.path');
        $response->assertJsonPath('data.0.lifecycle_state', FileLifecycleState::AVAILABLE->value);
        $this->assertDatabaseHas(FileModel::class, ['user_id' => $user->id]);
    }

    public function test_index_does_not_expose_download_url_for_non_available_files(): void
    {
        $user = $this->authUser();

        $pendingFile = FileModel::factory()->for($user)->create([
            'lifecycle_state' => FileLifecycleState::PENDING_UPLOAD,
        ]);

        $response = $this->get(route('api.files.index', [
            'ids' => [$pendingFile->id],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $pendingFile->id);
        $response->assertJsonPath('data.0.lifecycle_state', FileLifecycleState::PENDING_UPLOAD->value);
        $response->assertJsonPath('data.0.download_url', null);
        $response->assertJsonPath('data.0.download_expires_at', null);
    }

    public function test_index_ignores_client_supplied_user_ids_and_returns_only_authenticated_users_files(): void
    {
        // Auth user for testing
        $user = $this->authUser();
        $user2 = $this->getUser();

        $ownedFile = FileModel::factory()->for($user)->create();
        FileModel::factory()->for($user2)->create();

        // Send API Request with a foreign user_id that should be ignored
        $response = $this->get(route('api.files.index', [
            'user_ids' => [$user2->id],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownedFile->id);
        $response->assertJsonMissingPath('data.0.path');
    }

    public function test_index_filter_by_ids_stays_scoped_to_authenticated_owner(): void
    {
        $user = $this->authUser();
        $user2 = $this->getUser();

        $ownedTarget = FileModel::factory()->for($user)->create();
        FileModel::factory()->for($user)->create();
        $foreignTarget = FileModel::factory()->for($user2)->create();

        $response = $this->get(route('api.files.index', [
            'ids' => [$ownedTarget->id, $foreignTarget->id],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $ownedTarget->id);
    }

    public function test_destroy_success_filter_by_user_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Factory for testing
        $factory = new FileFactory();

        // Data for testing
        $files = [];
        $count = 3;
        for ($i = 0; $i < $count; $i++) {
            $files[] = $factory->for($user)->create([
                'key' => $this->disk->putFile(
                    'force-delete-command-test-handle-success',
                    UploadedFile::fake()->image('force-delete-command-test-handle-success.jpg'),
                ),
            ]);
        }

        // Filters for testing
        $filters = FilterFileDTO::from(['user_ids' => [$user->id]]);

        // Send API Request
        $response = $this->delete(route('api.files.destroy'), $filters->toArray());

        // Check asserts that files deleted with status code 204
        $response->assertNoContent();
        foreach ($files as $file) {
            $this->assertSoftDeleted($file);
        }
    }

    public function test_destroy_dispatches_auditable_event_per_soft_deleted_file_without_changing_status_code(): void
    {
        Event::fake();

        $user = $this->authUser();
        $deletedFiles = FileModel::factory(2)->for($user)->create();

        $response = $this->delete(route('api.files.destroy'), [
            'ids' => $deletedFiles->pluck('id')->all(),
        ]);

        $response->assertNoContent();
        Event::assertDispatchedTimes(FileSoftDeleted::class, $deletedFiles->count());
    }

    public function test_destroy_success_filter_by_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Factory for testing
        $factory = new FileFactory();

        // Filters for testing
        $filters = FilterFileDTO::from(['ids' => []]);

        // Data for testing
        $files = [];
        $count = 3;
        for ($i = 0; $i < $count; $i++) {
            $file = $factory->for($user)->create([
                'key' => $this->disk->putFile(
                    'force-delete-command-test-handle-success',
                    UploadedFile::fake()->image('force-delete-command-test-handle-success.jpg'),
                ),
            ]);

            $filters->ids[] = $file->id;
            $files[] = $file;
        }

        // Send API Request
        $response = $this->delete(route('api.files.destroy'), $filters->toArray());

        // Check asserts that files deleted with status code 204
        $response->assertNoContent();
        foreach ($files as $file) {
            $this->assertSoftDeleted($file);
        }
    }

    public function test_destroy_by_ids_deletes_only_selected_authenticated_users_files(): void
    {
        $user = $this->authUser();
        $user2 = $this->getUser();

        $ownedTarget = FileModel::factory()->for($user)->create();
        $ownedUntouched = FileModel::factory()->for($user)->create();
        $foreignTarget = FileModel::factory()->for($user2)->create();

        $response = $this->delete(route('api.files.destroy'), [
            'user_ids' => [$user2->id],
            'ids' => [$ownedTarget->id, $foreignTarget->id],
        ]);

        $response->assertNoContent();

        $this->assertSoftDeleted($ownedTarget);
        $this->assertNotSoftDeleted($ownedUntouched);
        $this->assertNotSoftDeleted($foreignTarget);
    }
}
