<?php

declare(strict_types=1);

namespace Presentation\Analys\Requests;

use Application\Analys\DTO\Requests\CreateUserAnalysisRequestDTO;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Enums\Unit;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Shared\Requests\BaseRequest;
use Shared\Rules\UserIdMatchesAuth;
use Shared\Rules\UserIdMatchesUrlUserId;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[OA\RequestBody(
    request: 'CreateUserAnalysisRequest',
    description: 'Create many UserAnalys Request',
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'user_id', description: 'User Id for analysis', type: 'string'),
            new OA\Property(property: 'analysis', properties: [
                new OA\Property(property: 'user_id', description: 'User Id for analys', type: 'string'),
                new OA\Property(property: 'analys_id', description: 'Analys ID', type: 'string', enum: Analys::class),
                new OA\Property(property: 'data', description: 'Value of analys', type: 'float'),
                new OA\Property(property: 'unit', description: 'Unit of measurement', type: 'string'),
            ]),
        ]
    )
)]
class CreateUserAnalysisRequest extends BaseRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->getUserIdFromPath() === auth()->user()?->id;
    }

    public function getDTO(): CreateUserAnalysisRequestDTO
    {
        if (! $validated = $this->validated()) {
            throw new UnprocessableEntityHttpException();
        }

        return CreateUserAnalysisRequestDTO::from($validated);
    }

    public function getUserIdFromPath(): string
    {
        return $this->route('userId');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'string', new UserIdMatchesAuth(), new UserIdMatchesUrlUserId()],

            'analysis' => ['required', 'array'],
            'analysis.*.user_id' => ['required', 'string', new UserIdMatchesAuth(), new UserIdMatchesUrlUserId()],
            'analysis.*.analys_id' => ['required', 'int', Rule::enum(Analys::class)],
            'analysis.*.data' => ['required', 'numeric'],
            'analysis.*.unit' => ['required', 'string', Rule::enum(Unit::class)],
        ];
    }
}
