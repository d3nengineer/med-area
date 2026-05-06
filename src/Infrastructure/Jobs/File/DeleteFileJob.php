<?php

declare(strict_types=1);

namespace Infrastructure\Jobs\File;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Shared\Enums\Storage as EnumsStorage;
use Shared\Exceptions\ServerErrorException;

class DeleteFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $path;
    protected EnumsStorage $diskName;

    public function __construct(string $path, ?EnumsStorage $diskName = null)
    {
        $this->path = $path;
        $this->diskName = $diskName ?? EnumsStorage::S3;
    }

    protected function getDisk(): Filesystem
    {
        return Storage::disk($this->diskName->value);
    }

    public function handle(): void
    {
        try {
            $disk = $this->getDisk();

            if ($disk->exists($this->path)) {
                $disk->delete($this->path);
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to delete file: ' . $this->path . '. ' . $e->getMessage());
            throw new ServerErrorException();
        }
    }
}
