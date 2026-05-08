<?php

declare(strict_types=1);

namespace Application\Storage\Commands;

use Application\Storage\Services\Contracts\FileLifecycleBackfillServiceContract;
use Illuminate\Console\Command;

class BackfillFileLifecycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-file-lifecycle {--apply : Persist inferred lifecycle changes} {--chunk=200 : Batch size}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill file lifecycle state for legacy rows in dry-run or apply mode.';

    public function __construct(
        private readonly FileLifecycleBackfillServiceContract $backfillService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $chunkSize = (int) $this->option('chunk');

        logger()->info('[BackfillFileLifecycleCommand.handle] starting lifecycle backfill', [
            'apply' => $apply,
            'chunk_size' => $chunkSize,
        ]);

        $report = $this->backfillService->backfill($apply, $chunkSize);

        $mode = $report->dry_run ? 'dry-run' : 'apply';

        $this->info('Lifecycle backfill completed in ' . $mode . ' mode.');
        $this->line('Scanned: ' . $report->scanned_count);
        $this->line('Updated: ' . $report->updated_count);
        $this->line('Manual review: ' . $report->manual_review_count);

        if ($report->manual_review_count > 0) {
            $this->warn('Manual review file IDs: ' . implode(', ', $report->manual_review_file_ids));
        }

        logger()->info('[BackfillFileLifecycleCommand.handle] lifecycle backfill completed', [
            'apply' => $apply,
            'scanned_count' => $report->scanned_count,
            'updated_count' => $report->updated_count,
            'manual_review_count' => $report->manual_review_count,
        ]);

        return self::SUCCESS;
    }
}
