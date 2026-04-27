<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Jobs\AI\Recogniser;

use Domain\AI\Recognise\DTO\RecogniseRequestDTO;
use Application\AI\Recogniser\DTO\Responses\RecogniseAsyncResponse;
use Application\AI\Recogniser\Services\Contracts\RecogniserServiceContract;
use Application\AI\Recogniser\Services\RecogniseResponseParser;
use Carbon\Carbon;
use Domain\AI\Recognise\Enums\RecogniseStatus;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Enums\Unit;
use Illuminate\Support\Facades\Log;
use Infrastructure\Jobs\AI\Recogniser\UpdateYVisionRecogniseRequestJob;
use Domain\AI\Recognise\Repositories\RecogniseRequestRepositoryContract;
use Mockery;
use Mockery\MockInterface;
use Tests\Unit\TestCase;

class UpdateYVisionRecogniseRequestJobTest extends TestCase
{
    private RecogniseRequestRepositoryContract|MockInterface $repositoryMock;

    private RecogniserServiceContract|MockInterface $recogniserServiceMock;

    private RecogniseResponseParser $parser;

    public function setUp(): void
    {
        parent::setUp();

        $this->repositoryMock = Mockery::mock(RecogniseRequestRepositoryContract::class);
        $this->recogniserServiceMock = Mockery::mock(RecogniserServiceContract::class);
        $this->parser = new RecogniseResponseParser();

        $this->app->instance(RecogniseRequestRepositoryContract::class, $this->repositoryMock);
        $this->app->instance(RecogniserServiceContract::class, $this->recogniserServiceMock);
        $this->app->instance(RecogniseResponseParser::class, $this->parser);
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test successful recognition with done response and D3 analysis
     */
    public function test_handle_with_done_response_updates_status_to_success(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $expectedData = '45.5';
        $expectedUnit = Unit::GL;
        $expectedAnalys = Analys::D3;

        $response = $this->createMockResponse([
            'blocks' => [
                [
                    'lines' => [
                        ['text' => $expectedAnalys->name],
                        ['text' => $expectedData . ' г/л'],
                    ],
                ],
            ],
            'fullText' => "{$expectedAnalys->name}\n{$expectedData} г/л\n",
        ]);

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('info')
            ->once()
            ->with('Recognition completed successfully', Mockery::on(function ($context): bool {
                return isset($context['operation_id'])
                    && isset($context['recognised_items'])
                    && isset($context['response']);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::SUCCESS, $updateCallArguments->status);
        $this->assertArrayHasKey('result', $updateCallArguments->response);
        $this->assertCount(1, $updateCallArguments->response['result']);
        $this->assertSame($expectedAnalys->value, $updateCallArguments->response['result'][0]['analys_id']);
        $this->assertSame($expectedData, $updateCallArguments->response['result'][0]['data']);
        $this->assertSame($expectedUnit->value, $updateCallArguments->response['result'][0]['unit']);
    }

    /**
     * Test recognition not done yet - job should retry
     */
    public function test_handle_with_not_done_response_logs_and_retries(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $response = new RecogniseAsyncResponse();
        $response->id = 'test-operation-id';
        $response->description = 'Test recognition';
        $response->createdAt = Carbon::now();
        $response->createdBy = 'test-user';
        $response->modifiedAt = Carbon::now();
        $response->done = false;
        $response->metadata = null;

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->never();

        Log::shouldReceive('info')
            ->once()
            ->with('Recognition is not done yet, will retry', Mockery::on(function ($context): bool {
                return isset($context['operation_id'])
                    && isset($context['attempt']);
            }))
            ->andReturnNull();

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test successful recognition without ID in request
     */
    public function test_handle_with_done_response_but_no_id_does_not_update(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $response = $this->createMockResponse([
            'blocks' => [
                [
                    'lines' => [
                        ['text' => 'D3'],
                        ['text' => '45.5 г/л'],
                    ],
                ],
            ],
            'fullText' => "D3\n45.5 г/л\n",
        ]);

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->never();

        Log::shouldReceive('info')
            ->once()
            ->with('Recognition completed successfully', Mockery::on(function ($context): bool {
                return isset($context['operation_id'])
                    && isset($context['recognised_items'])
                    && isset($context['response']);
            }))
            ->andReturnNull();

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test failed recognition - exception thrown
     */
    public function test_failed_updates_status_to_failed_with_error_message(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $exception = new \Exception('OCR API timeout');

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get recognition data from Yandex Vision OCR', Mockery::on(function ($context): bool {
                return array_key_exists('class', $context)
                    && array_key_exists('method', $context)
                    && array_key_exists('operation_id', $context)
                    && array_key_exists('message', $context);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->failed($exception);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::FAILED, $updateCallArguments->status);
        $this->assertSame('OCR API timeout', $updateCallArguments->failed_reason);
    }

    /**
     * Test failed recognition with null exception
     */
    public function test_failed_with_null_exception_uses_default_message(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get recognition data from Yandex Vision OCR', Mockery::on(function ($context): bool {
                return array_key_exists('class', $context)
                    && array_key_exists('method', $context)
                    && array_key_exists('operation_id', $context)
                    && array_key_exists('message', $context);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->failed(null);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::FAILED, $updateCallArguments->status);
        $this->assertSame('Unknown error occurred', $updateCallArguments->failed_reason);
    }

    /**
     * Test failed recognition without ID in request
     */
    public function test_failed_without_id_does_not_update_repository(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $exception = new \Exception('OCR API timeout');

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->never();

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get recognition data from Yandex Vision OCR', Mockery::on(function ($context): bool {
                return array_key_exists('class', $context)
                    && array_key_exists('method', $context)
                    && array_key_exists('operation_id', $context)
                    && array_key_exists('message', $context);
            }))
            ->andReturnNull();

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->failed($exception);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test successful recognition with multiple analyses
     */
    public function test_handle_with_multiple_analyses_in_response(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $expectedAnalyses = [
            ['analys' => Analys::D3, 'data' => '45.5', 'unit' => Unit::GL],
            ['analys' => Analys::B6, 'data' => '12.3', 'unit' => Unit::GL],
            ['analys' => Analys::B9, 'data' => '8.5', 'unit' => Unit::PERCENT],
        ];

        $lines = [];
        $fullTextParts = [];
        foreach ($expectedAnalyses as $item) {
            $lines[] = ['text' => $item['analys']->name];
            $lines[] = ['text' => $item['data'] . ' ' . $item['unit']->value];
            $fullTextParts[] = $item['analys']->name;
            $fullTextParts[] = $item['data'] . ' ' . $item['unit']->value;
        }

        $response = $this->createMockResponse([
            'blocks' => [['lines' => $lines]],
            'fullText' => implode("\n", $fullTextParts) . "\n",
        ]);

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('info')
            ->once()
            ->with('Recognition completed successfully', Mockery::on(function ($context): bool {
                return isset($context['operation_id'])
                    && isset($context['recognised_items'])
                    && isset($context['response']);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::SUCCESS, $updateCallArguments->status);
        $this->assertCount(count($expectedAnalyses), $updateCallArguments->response['result']);

        foreach ($expectedAnalyses as $index => $expected) {
            $this->assertSame($expected['analys']->value, $updateCallArguments->response['result'][$index]['analys_id']);
            $this->assertSame($expected['data'], $updateCallArguments->response['result'][$index]['data']);
            $this->assertSame($expected['unit']->value, $updateCallArguments->response['result'][$index]['unit']);
        }
    }

    /**
     * Test successful recognition with empty response
     */
    public function test_handle_with_empty_recognition_result(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $response = $this->createMockResponse([
            'blocks' => [],
            'fullText' => '',
        ]);

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('info')
            ->once()
            ->with('Recognition completed successfully', Mockery::on(function ($context): bool {
                return isset($context['operation_id'])
                    && isset($context['recognised_items'])
                    && isset($context['response']);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::SUCCESS, $updateCallArguments->status);
        $this->assertCount(0, $updateCallArguments->response['result']);
    }

    /**
     * Test job has correct retry configuration
     */
    public function test_job_has_correct_retry_configuration(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->operation_id = 'test-operation-id';

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);

        $this->assertEquals(5, $job->tries);
        $this->assertEquals([30, 60, 90, 120], $job->backoff);
    }

    /**
     * Test successful recognition with null metadata
     */
    public function test_handle_with_null_metadata_in_response(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $response = new RecogniseAsyncResponse();
        $response->id = 'test-operation-id';
        $response->description = 'Test recognition';
        $response->createdAt = Carbon::now();
        $response->createdBy = 'test-user';
        $response->modifiedAt = Carbon::now();
        $response->done = true;
        $response->metadata = null;

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('info')
            ->once()
            ->with('Recognition completed successfully', Mockery::on(function ($context): bool {
                return isset($context['operation_id'])
                    && isset($context['recognised_items'])
                    && isset($context['response']);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::SUCCESS, $updateCallArguments->status);
        $this->assertCount(0, $updateCallArguments->response['result']);
    }

    /**
     * Test failed with Error exception (Throwable)
     */
    public function test_failed_with_error_exception(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $exception = new \Error('Custom error');

        $updateCallArguments = null;

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->with(1, Mockery::capture($updateCallArguments))
            ->andReturn($recogniseRequestDTO);
        /** @var RecogniseRequestDTO $updateCallArguments */

        Log::shouldReceive('error')
            ->once()
            ->with('Failed to get recognition data from Yandex Vision OCR', Mockery::on(function ($context): bool {
                return array_key_exists('class', $context)
                    && array_key_exists('method', $context)
                    && array_key_exists('operation_id', $context)
                    && array_key_exists('message', $context);
            }));

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->failed($exception);

        $this->assertInstanceOf(RecogniseRequestDTO::class, $updateCallArguments);
        $this->assertSame(RecogniseStatus::FAILED, $updateCallArguments->status);
        $this->assertSame('Custom error', $updateCallArguments->failed_reason);
    }

    /**
     * Test handleSuccess logs correct information
     */
    public function test_handle_success_logs_correct_information(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $response = $this->createMockResponse([
            'blocks' => [
                [
                    'lines' => [
                        ['text' => 'D3'],
                        ['text' => '45.5 г/л'],
                    ],
                ],
            ],
            'fullText' => "D3\n45.5 г/л\n",
        ]);

        $this->recogniserServiceMock
            ->shouldReceive('getRecognition')
            ->once()
            ->with($recogniseRequestDTO)
            ->andReturn($response);

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->andReturn($recogniseRequestDTO);

        Log::shouldReceive('info')
            ->once()
            ->with(
                'Recognition completed successfully',
                Mockery::on(function ($context): bool {
                    return $context['operation_id'] === 'test-operation-id'
                        && $context['recognised_items'] === 1
                        && isset($context['response']);
                })
            )
            ->andReturnNull();

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->handle($this->repositoryMock, $this->recogniserServiceMock, $this->parser);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Test failed logs correct error information
     */
    public function test_failed_logs_correct_error_information(): void
    {
        $recogniseRequestDTO = new RecogniseRequestDTO();
        $recogniseRequestDTO->id = 1;
        $recogniseRequestDTO->operation_id = 'test-operation-id';
        $recogniseRequestDTO->status = RecogniseStatus::PROCESSED;

        $exception = new \Exception('Test error message');

        $this->repositoryMock
            ->shouldReceive('updateById')
            ->once()
            ->andReturn($recogniseRequestDTO);

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Failed to get recognition data from Yandex Vision OCR',
                Mockery::on(function ($context): bool {
                    return $context['class'] === UpdateYVisionRecogniseRequestJob::class
                        && $context['method'] === 'handle'
                        && $context['operation_id'] === 'test-operation-id'
                        && $context['message'] === 'Test error message';
                })
            )
            ->andReturnNull();

        $job = new UpdateYVisionRecogniseRequestJob($recogniseRequestDTO);
        $job->failed($exception);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Create mock RecogniseAsyncResponse with real Yandex Vision OCR format
     *
     * @param array{blocks: list<array<string, mixed>>, fullText: string}|null $textAnnotation
     */
    protected function createMockResponse(?array $textAnnotation): RecogniseAsyncResponse
    {
        $response = new RecogniseAsyncResponse();
        $response->id = 'test-operation-id';
        $response->description = 'Test recognition';
        $response->createdAt = Carbon::now();
        $response->createdBy = 'test-user';
        $response->modifiedAt = Carbon::now();
        $response->done = true;
        $response->metadata = $textAnnotation !== null ? ['textAnnotation' => $textAnnotation] : null;

        return $response;
    }
}
