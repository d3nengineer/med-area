<?php

declare(strict_types=1);

namespace Domain\Audit\Enums;

enum UserActivityEntityType: string
{
    case FILE = 'file';
    case ANALYSIS = 'analysis';
}
