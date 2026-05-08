<?php

declare(strict_types=1);

namespace Application\Storage\DTO;

use Shared\DTO\BaseDTO;

class BackfillFileLifecycleReportDTO extends BaseDTO
{
    public bool $dry_run;

    public int $scanned_count;

    public int $updated_count;

    public int $manual_review_count;

    /** @var array<string> */
    public array $updated_file_ids;

    /** @var array<string> */
    public array $manual_review_file_ids;
}
