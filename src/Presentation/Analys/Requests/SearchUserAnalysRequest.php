<?php

declare(strict_types=1);

namespace Presentation\Analys\Requests;

use Domain\Analys\DTO\Filters\SearchUserAnalysDTO;
use Shared\Requests\BaseRequest;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SearchUserAnalysRequest extends BaseRequest
{
    public function authorize(): bool
    {
        $authenticatedUserId = $this->user()?->getAuthIdentifier();

        if ($authenticatedUserId === null) {
            return false;
        }

        return (string) $this->route('userId') === (string) $authenticatedUserId;
    }

    public function getDTO(): SearchUserAnalysDTO
    {
        if (! $validated = $this->validated()) {
            throw new UnprocessableEntityHttpException();
        }

        return new SearchUserAnalysDTO(
            query: $validated['q'],
            userId: (string) $this->route('userId'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:255'],
        ];
    }
}
