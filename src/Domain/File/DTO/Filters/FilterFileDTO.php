<?php

declare(strict_types=1);

namespace Domain\File\DTO\Filters;

use Carbon\Carbon;
use Domain\File\Enums\FileLifecycleState;
use Shared\DTO\FilterBaseDTO;
use Spatie\LaravelData\Optional;

class FilterFileDTO extends FilterBaseDTO
{
    /** @var array<string> $ids */
    public array|Optional $ids;

    /** @var array<string> $user_ids */
    public array|Optional $user_ids;

    /** @var array<FileLifecycleState|string> $lifecycle_states */
    public array|Optional $lifecycle_states;

    /** @var array<string> $storage_operation_ids */
    public array|Optional $storage_operation_ids;

    // Range for size
    public int|Optional $min_size;
    public int|Optional $max_size;

    // Range for deleted_at
    public Carbon|Optional|null $min_deleted_at;
    public Carbon|Optional|null $max_deleted_at;

    // Range for lifecycle_changed_at
    public Carbon|Optional|null $min_lifecycle_changed_at;
    public Carbon|Optional|null $max_lifecycle_changed_at;
}
