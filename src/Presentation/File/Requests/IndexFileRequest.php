<?php

declare(strict_types=1);

namespace Presentation\File\Requests;

use Domain\File\DTO\Filters\FilterFileDTO;
use Shared\Requests\BaseRequest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class IndexFileRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function getDTO(): FilterFileDTO
    {
        $validated = $this->validated();

        if (! is_string($this->user()?->id)) {
            throw new AccessDeniedHttpException();
        }

        return FilterFileDTO::from([
            ...$validated,
            'user_ids' => [$this->user()->id],
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // id
            'ids' => ['array'],
            'ids.*' => ['string'],
        ];
    }
}
