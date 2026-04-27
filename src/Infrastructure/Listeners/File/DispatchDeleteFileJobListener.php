<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\File;

use Domain\File\Events\FileMarkedForDeletion;
use Infrastructure\Jobs\File\DeleteFileJob;

class DispatchDeleteFileJobListener
{
    public function handle(FileMarkedForDeletion $event): void
    {
        DeleteFileJob::dispatch($event->key, $event->diskName);
    }
}
