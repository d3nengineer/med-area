<?php

declare(strict_types=1);

namespace Domain\File\DTO;

use Carbon\Carbon;
use Domain\File\Enums\FileLifecycleState;
use Illuminate\Http\UploadedFile;
use Shared\DTO\BaseDTO;
use Shared\Enums\Storage;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Optional;

class FileDTO extends BaseDTO
{
    public string|Optional $id;

    public string|Optional $user_id;

    public Storage $storage;

    public string|Optional $endpoint;

    public string|Optional $bucket;

    public string $key;

    public int|Optional $size;

    public UploadedFile|Optional $content;

    public FileLifecycleState|Optional $lifecycle_state;

    public string|Optional|null $storage_operation_id;

    public string|Optional|null $storage_error_code;

    public string|Optional|null $storage_error_message;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon|Optional|null $lifecycle_changed_at;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon|Optional|null $storage_reconciled_at;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon|Optional|null $upload_completed_at;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon|Optional|null $delete_requested_at;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon|Optional $created_at;

    #[WithCast(DateTimeInterfaceCast::class)]
    public string|Optional $updated_at;

    #[WithCast(DateTimeInterfaceCast::class)]
    public string|Optional $deleted_at;
}
