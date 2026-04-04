<?php

declare(strict_types=1);

namespace Presentation\Analys\Controllers;

use Application\Analys\Services\Contracts\UserAnalysSearchServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use OpenApi\Attributes as OA;
use Presentation\Analys\Requests\SearchUserAnalysRequest;
use Presentation\BaseController;

class UserAnalysSearchController extends BaseController
{
    public function __construct(
        protected readonly UserAnalysSearchServiceContract $searchService,
    ) {}

    #[OA\Get(
        path: '/api/users/{userId}/analysis/search',
        operationId: 'apiUsersAnalysisSearch',
        description: 'Full-text search user analyses.',
        tags: ['analys', 'api'],
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', description: 'user id', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'q', in: 'query', description: 'search query (min 2 chars)', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Search results.')]
    #[OA\Response(response: 401, description: 'Unauthorized.')]
    #[OA\Response(response: 422, description: 'Validation error.')]
    public function __invoke(SearchUserAnalysRequest $request): JsonResponse
    {
        $dto = $request->getDTO();

        logger()->debug('[UserAnalysSearchController] search request', [
            'userId' => $dto->userId,
            'q'      => $dto->query,
        ]);

        $results = $this->searchService->search($dto);

        return Response::json($results->values());
    }
}
