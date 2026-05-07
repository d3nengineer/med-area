<?php

declare(strict_types=1);

namespace Domain\Audit\Enums;

enum UserActivityAction: string
{
    case UPLOADED = 'uploaded';
    case SOFT_DELETED = 'soft_deleted';
    case CREATED = 'created';
    case DELETED = 'deleted';
}
