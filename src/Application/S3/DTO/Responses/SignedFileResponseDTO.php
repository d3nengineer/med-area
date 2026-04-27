<?php

declare(strict_types=1);

namespace Application\S3\DTO\Responses;

use Carbon\Carbon;
use Shared\DTO\BaseDTO;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Optional;

class SignedFileResponseDTO extends BaseDTO
{
    public string|Optional $id;

    public string|Optional $user_id;

    public string $download_url;

    #[WithCast(DateTimeInterfaceCast::class)]
    public Carbon $download_expires_at;
}
