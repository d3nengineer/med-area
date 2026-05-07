<?php

declare(strict_types=1);

namespace Domain\Audit\DTO;

use Carbon\Carbon;
use Domain\Audit\Enums\UserActivityAction;
use Domain\Audit\Enums\UserActivityEntityType;
use Shared\DTO\BaseDTO;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Optional;

class UserActivityAuditDTO extends BaseDTO
{
    public string|Optional $event_id;

    public UserActivityAction|Optional $action;

    public UserActivityEntityType|Optional $entity_type;

    public string|Optional $entity_id;

    public string|Optional|null $actor_user_id;

    public string|Optional|null $subject_user_id;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon|Optional|null $occurred_at;

    public string|Optional $source;

    /** @var array<string, mixed> */
    public array $metadata;
}
