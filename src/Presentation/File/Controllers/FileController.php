<?php

declare(strict_types=1);

namespace Presentation\File\Controllers;

use Application\File\Services\Contracts\FileServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Presentation\BaseController;
use Presentation\File\Requests\DeleteFilesRequest;
use Presentation\File\Requests\IndexFileRequest;
use Presentation\File\Requests\UploadFilesRequest;
use Presentation\File\Resources\FileResourceCollection;
use OpenApi\Attributes as OA;

class FileController extends BaseController
{
    public function __construct(
        protected readonly FileServiceContract $fileService,
    ) {}

    #[OA\Post(
        path: '/api/files',
        operationId: 'apiFilesUpload',
        description: 'Add files for user.',
        tags: ['file', 'api'],
        requestBody: new OA\RequestBody(ref: UploadFilesRequest::class),
    )]
    #[OA\Response(
        response: 201,
        description: 'Files for user added.',
        content: new OA\JsonContent(ref: FileResourceCollection::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden.',
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error.',
    )]
    public function upload(UploadFilesRequest $request): JsonResponse
    {
        $responses = $this->fileService->upload($request->getDTO());

        return Response::json(FileResourceCollection::make($responses), 201);
    }

    #[OA\Get(
        path: '/api/files',
        operationId: 'apiFilesIndex',
        description: 'Get authenticated user files by filters.',
        tags: ['file', 'api'],
        parameters: [
            new OA\Parameter(
                name: 'ids[]',
                in: 'query',
                description: 'Array of files IDs',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                ),
            ),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Files data.',
        content: new OA\JsonContent(ref: FileResourceCollection::class)
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden.',
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error.',
    )]
    public function index(IndexFileRequest $request): JsonResponse
    {
        return Response::json(new FileResourceCollection($this->fileService->index($request->getDTO())), 200);
    }

    #[OA\Delete(
        path: '/api/files',
        operationId: 'apiFilesDestroy',
        description: 'Delete authenticated user files by filters.',
        tags: ['file', 'api'],
        parameters: [
            new OA\Parameter(
                name: 'ids[]',
                in: 'query',
                description: 'Array of files IDs',
                schema: new OA\Schema(
                    type: 'array',
                    items: new OA\Items(type: 'string'),
                ),
            ),
        ]
    )]
    #[OA\Response(
        response: 204,
        description: 'Files deleted.',
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized.',
    )]
    #[OA\Response(
        response: 403,
        description: 'Forbidden.',
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error.',
    )]
    public function destroy(DeleteFilesRequest $request): JsonResponse
    {
        $this->fileService->delete($request->getDTO());

        return Response::json(null, 204);
    }
}
