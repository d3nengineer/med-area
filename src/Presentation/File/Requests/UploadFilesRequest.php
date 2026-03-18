<?php

declare(strict_types=1);

namespace Presentation\File\Requests;

use Domain\File\DTO\FileDTO;
use Application\S3\DTO\Requests\CreateFilesRequestDTO;
use Illuminate\Http\UploadedFile;
use OpenApi\Attributes as OA;
use Shared\Requests\BaseRequest;
use Shared\Rules\UserIdMatchesAuth;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[OA\RequestBody(
    request: 'UploadFilesRequest',
    description: 'Upload files request.',
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['user_id', 'files'],
            properties: [
                new OA\Property(
                    property: 'user_id',
                    description: 'User ID',
                    type: 'integer',
                    format: 'int64',
                ),
                new OA\Property(
                    property: 'files',
                    description: 'List of files. Allowed types: pdf, jpg, jpeg, png, webp. Max size: 15MB per file.',
                    type: 'array',
                    items: new OA\Items(
                        type: 'string',
                        format: 'binary',
                    )
                ),
            ]
        )
    )
)]
class UploadFilesRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function getDTO(): CreateFilesRequestDTO
    {
        if (! $validated = $this->validated()) {
            throw new UnprocessableEntityHttpException();
        }

        $dto = CreateFilesRequestDTO::from([]);
        $dto->files = [];

        foreach ($validated['files'] as $file) {
            /** @var UploadedFile $file */

            $dto->files[] = FileDTO::from([
                'user_id' => $this->user()?->id,
                'bucket' => config('filesystems.disks.s3.bucket'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'size' => $file->getSize(),
                'content' => $file,
                'key' => microtime(),
            ]);
        }

        return $dto;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', new UserIdMatchesAuth()],

            'files' => ['required', 'array'],
            'files.*' => ['required', 'file', 'max:15000', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }
}
