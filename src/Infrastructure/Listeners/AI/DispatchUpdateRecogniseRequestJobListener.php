<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\AI;

use Domain\AI\Recognise\Events\RecogniseRequestCompleted;
use Infrastructure\Jobs\AI\Recogniser\UpdateYVisionRecogniseRequestJob;

class DispatchUpdateRecogniseRequestJobListener
{
    public function handle(RecogniseRequestCompleted $event): void
    {
        UpdateYVisionRecogniseRequestJob::dispatch($event->recogniseRequestDTO)
            ->delay(now()->plus(seconds: 40));
    }
}
