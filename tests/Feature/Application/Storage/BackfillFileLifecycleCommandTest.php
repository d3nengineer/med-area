<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Storage;

use Application\Storage\Commands\BackfillFileLifecycleCommand;
use Domain\File\Enums\FileLifecycleState;
use Domain\File\Models\File;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillFileLifecycleCommandTest extends TestCase
{
    public function test_command_dry_run_reports_candidates_without_persisting_changes(): void
    {
        Artisan::addCommands([
            BackfillFileLifecycleCommand::class,
        ]);

        $user = $this->getUser();

        $deletedFile = File::factory()->for($user)->create([
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'delete_requested_at' => null,
        ]);
        $deletedFile->delete();

        $this->artisan('app:backfill-file-lifecycle')
            ->assertSuccessful();

        $freshDeletedFile = File::query()->withTrashed()->findOrFail($deletedFile->id);

        $this->assertSame(FileLifecycleState::AVAILABLE, $freshDeletedFile->lifecycle_state);
        $this->assertNull($freshDeletedFile->delete_requested_at);
    }

    public function test_command_apply_backfills_soft_deleted_rows_and_missing_timestamps(): void
    {
        Artisan::addCommands([
            BackfillFileLifecycleCommand::class,
        ]);

        $user = $this->getUser();

        $activeFile = File::factory()->for($user)->create([
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'lifecycle_changed_at' => null,
            'storage_reconciled_at' => null,
            'upload_completed_at' => null,
        ]);

        $deletedFile = File::factory()->for($user)->create([
            'lifecycle_state' => FileLifecycleState::AVAILABLE,
            'delete_requested_at' => null,
            'lifecycle_changed_at' => null,
        ]);
        $deletedFile->delete();

        $this->artisan('app:backfill-file-lifecycle', [
            '--apply' => true,
        ])->assertSuccessful();

        $freshActiveFile = File::query()->findOrFail($activeFile->id);
        $freshDeletedFile = File::query()->withTrashed()->findOrFail($deletedFile->id);

        $this->assertSame(FileLifecycleState::AVAILABLE, $freshActiveFile->lifecycle_state);
        $this->assertNotNull($freshActiveFile->lifecycle_changed_at);
        $this->assertNotNull($freshActiveFile->storage_reconciled_at);
        $this->assertNotNull($freshActiveFile->upload_completed_at);

        $this->assertSame(FileLifecycleState::DELETING, $freshDeletedFile->lifecycle_state);
        $this->assertNotNull($freshDeletedFile->delete_requested_at);
        $this->assertNotNull($freshDeletedFile->lifecycle_changed_at);
    }
}
