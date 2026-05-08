<?php

declare(strict_types=1);

namespace Domain\File\Enums;

enum FileLifecycleState: string
{
    case PENDING_UPLOAD = 'pending_upload';

    case AVAILABLE = 'available';

    case DELETING = 'deleting';

    case FAILED = 'failed';
}
