<?php

declare(strict_types=1);

namespace Application\Storage\Services\Contracts;

use Application\Storage\DTO\BackfillFileLifecycleReportDTO;

interface FileLifecycleBackfillServiceContract
{
    public function backfill(bool $apply, int $chunkSize = 200): BackfillFileLifecycleReportDTO;
}
