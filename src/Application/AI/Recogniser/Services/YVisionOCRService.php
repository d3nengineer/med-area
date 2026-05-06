<?php

declare(strict_types=1);

namespace Application\AI\Recogniser\Services;

use Domain\AI\Recognise\DTO\RecogniseRequestDTO;
use Application\AI\Recogniser\DTO\RecogniseURLParamsDTO;
use Application\AI\Recogniser\DTO\Requests\RecogniseAsyncRequestDTO;
use Application\AI\Recogniser\DTO\Responses\RecogniseAsyncResponse;
use Application\AI\Recogniser\Services\Contracts\RecogniseRequestServiceContract;
use Application\AI\Recogniser\Services\Contracts\RecogniserServiceContract;
use Domain\AI\Recognise\Enums\RecogniseStatus;
use Illuminate\Http\Client\Factory;
use Domain\AI\Recognise\Events\RecogniseRequestCompleted;
use Shared\Enums\AuthTokenType;
use Shared\Exceptions\ServerErrorException;
use Shared\Services\BaseExternalService;

/**
 * Yandex Vision OCR Service
 *
 * See: https://yandex.cloud/ru/docs/vision/
 */
class YVisionOCRService extends BaseExternalService implements RecogniserServiceContract
{
    protected string $module = 'ocr';

    public function __construct(
        protected readonly RecogniseRequestServiceContract $recogniseRequestService,
        protected readonly Factory $http,
    ) {
        parent::__construct();

        $authToken = config('yc.' . $this->module . '.secret');
        $this->setAuthorization($authToken, AuthTokenType::API_KEY);

        $this->setURLParams(RecogniseURLParamsDTO::from([
            'endpoint' => config('yc.' . $this->module . '.endpoint'),
            'version' => config('yc.' . $this->module . '.version'),
            'module' => $this->module,
        ]));
    }

    public function recogniseAsync(RecogniseAsyncRequestDTO $request, RecogniseRequestDTO $recogniseRequestDTO): RecogniseRequestDTO
    {
        $recogniseRequestId = $recogniseRequestDTO->toArray()['id'] ?? null;

        $this->setURLResourceParam('recognizeTextAsync');

        try {
            $response = $this->http->withHeaders($this->getBaseHeaders())
                ->withUrlParameters($this->urlParams->toArray())
                ->post(
                    $this->getURITemplate(),
                    $request->toArray(),
                );

            if ($response->failed()) {
                $response->throw();
            }

            $responseDTO = RecogniseAsyncResponse::from($response->json());

            if ($response->successful()) {
                $recogniseRequestDTO->operation_id = $responseDTO->id;
                $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

                if (\is_int($recogniseRequestId)) {
                    $this->recogniseRequestService->updateById($recogniseRequestId, $recogniseRequestDTO);
                }

                // Queue for periodic recognition status checking via Domain Event
                event(new RecogniseRequestCompleted($recogniseRequestDTO));
            }

            return $recogniseRequestDTO;

        } catch (\Throwable $e) {
            logger()->error('[YVisionOCRService.recogniseAsync] failed to start async recognition', [
                'error'   => $e->getMessage(),
                'context' => ['recognise_request_id' => $recogniseRequestId],
            ]);
            throw new ServerErrorException();
        }
    }

    public function getRecognition(RecogniseRequestDTO $recogniseRequest): RecogniseAsyncResponse
    {
        $this->setURLResourceParam('getRecognition');

        try {
            $response = $this->http->withHeaders($this->getBaseHeaders())
                ->withUrlParameters($this->urlParams->toArray())
                ->get(
                    $this->getURITemplate(),
                    [
                        'operationId' => $recogniseRequest->operation_id,
                    ],
                );

            if ($response->failed()) {
                $response->throw();
            }

            return RecogniseAsyncResponse::from($response->json('result'));

        } catch (\Throwable $e) {
            logger()->error('[YVisionOCRService.getRecognition] failed to get recognition result', [
                'error'   => $e->getMessage(),
                'context' => ['operation_id' => $recogniseRequest->operation_id],
            ]);
            throw new ServerErrorException();
        }
    }

    public function getURITemplate(): string
    {
        return '{+endpoint}/{module}/{version}/{resource}';
    }
}
