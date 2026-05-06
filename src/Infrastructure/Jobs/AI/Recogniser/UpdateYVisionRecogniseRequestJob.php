<?php

declare(strict_types=1);

namespace Infrastructure\Jobs\AI\Recogniser;

use Domain\AI\Recognise\DTO\RecogniseRequestDTO;
use Application\AI\Recogniser\DTO\Responses\RecogniseAsyncResponse;
use Application\AI\Recogniser\Services\Contracts\RecogniserServiceContract;
use Application\AI\Recogniser\Services\RecogniseResponseParser;
use Domain\AI\Recognise\Enums\RecogniseStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Domain\AI\Recognise\Repositories\RecogniseRequestRepositoryContract;
use Throwable;

class UpdateYVisionRecogniseRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 90, 120];

    protected readonly RecogniseRequestDTO $recogniseRequest;

    /**
     * Create a new job instance.
     */
    public function __construct(
        RecogniseRequestDTO $recogniseRequest,
    ) {
        $this->recogniseRequest = $recogniseRequest;
    }

    /**
     * Execute the job.
     */
    public function handle(
        RecogniseRequestRepositoryContract $recogniseRequestRepository,
        RecogniserServiceContract $recogniserService,
        RecogniseResponseParser $recogniseResponseParser,
    ): void {
        $response = $recogniserService->getRecognition($this->recogniseRequest);

        if ($response->done) {
            $this->handleSuccess($response, $recogniseRequestRepository, $recogniseResponseParser);
            return;
        }

        logger()->info('Recognition is not done yet, will retry', [
            'operation_id' => $this->recogniseRequest->operation_id,
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Handle successful recognition.
     * Parse OCR response and save structured analysis data.
     */
    protected function handleSuccess(
        RecogniseAsyncResponse $response,
        RecogniseRequestRepositoryContract $recogniseRequestRepository,
        RecogniseResponseParser $recogniseResponseParser,
    ): void {
        $recognisedData = $recogniseResponseParser->parse($response);

        $parsedResponse = [
            'result' => array_map(
                fn($item) => $item->toArray(),
                $recognisedData,
            ),
        ];

        $data = RecogniseRequestDTO::from([
            'status' => RecogniseStatus::SUCCESS,
            'response' => $parsedResponse,
        ]);

        $id = $this->recogniseRequest->toArray()['id'] ?? null;
        if (\is_int($id)) {
            $recogniseRequestRepository->updateById($id, $data);
        }

        logger()->info('Recognition completed successfully', [
            'operation_id' => $this->recogniseRequest->operation_id,
            'recognised_items' => count($recognisedData),
            'response' => $parsedResponse,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * Description: update status recognise request model to failed
     */
    public function failed(?Throwable $exception): void
    {
        $data = RecogniseRequestDTO::from([
            'status' => RecogniseStatus::FAILED,
            'failed_reason' => $exception?->getMessage() ?? 'Unknown error occurred',
        ]);

        $id = $this->recogniseRequest->toArray()['id'] ?? null;
        if (\is_int($id)) {
            app(RecogniseRequestRepositoryContract::class)->updateById($id, $data);
        }

        logger()->error('Failed to get recognition data from Yandex Vision OCR', [
            'class' => UpdateYVisionRecogniseRequestJob::class,
            'method' => 'handle',
            'operation_id' => $this->recogniseRequest->operation_id,
            'message' => $exception?->getMessage(),
        ]);
    }

}
