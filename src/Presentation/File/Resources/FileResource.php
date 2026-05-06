<?php

declare(strict_types=1);

namespace Presentation\File\Resources;

use Application\S3\DTO\Responses\SignedFileResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'FileResource',
    properties: [
        new OA\Property(property: 'id', description: 'id of File', type: 'string', format: 'uuid'),
        new OA\Property(property: 'user_id', description: 'user_id of File', type: 'string', format: 'uuid'),
        new OA\Property(property: 'download_url', description: 'Temporary signed download URL', type: 'string'),
        new OA\Property(property: 'download_expires_at', description: 'Signed URL expiration datetime', type: 'string', format: 'date-time'),
    ],
)]
/**
 * @mixin SignedFileResponseDTO
 */
class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource = SignedFileResponseDTO::from($this->resource);

        return [
            'id' => $this->resource->id,
            'user_id' => $this->resource->user_id,
            'download_url' => $this->resource->download_url,
            'download_expires_at' => $this->resource->download_expires_at,
        ];
    }
}
