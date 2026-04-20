<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation\Analys\Controllers;

use Application\AI\Recogniser\DTO\Requests\RecogniseAsyncRequestDTO;
use Domain\AI\Recognise\Enums\RecogniseStatus;
use Domain\AI\Recognise\Enums\YC\OCRModel;
use Domain\AI\Recognise\Models\RecogniseRequest;
use Domain\File\Models\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Shared\Enums\LanguageCode;
use Shared\Enums\Storage as EnumsStorage;
use Tests\TestCase;

class RecogniserControllerTest extends TestCase
{
    public function test_store_success(): void
    {
        // Fake queue to prevent job execution
        Queue::fake();
        $timestamp = now()->format('Y-m-d\TH:i:sP');
        Http::fake([
            '*' => Http::response([
                'id' => 'test-operation-id',
                'description' => 'Recognition started',
                'createdAt' => $timestamp,
                'createdBy' => 'test-user',
                'modifiedAt' => $timestamp,
                'done' => false,
                'metadata' => null,
            ], 200),
        ]);

        // Auth user for testing
        $user = $this->authUser();

        // Create File model
        $fileKey = 'tests/recogniser/analysis-example-1.xlsx';
        $file = Storage::disk(EnumsStorage::LOCAL)->get($fileKey);
        $fileModel = File::factory()->createOne([
            'user_id' => $user->id,
            'storage' => EnumsStorage::LOCAL,
            'key' => $fileKey,
        ]);

        $request = RecogniseAsyncRequestDTO::from([
            'content' => $file,
            'mimeType' => 'xlsx',
            'languageCodes' => [fake()->randomElement(LanguageCode::cases())],
            'model' => OCRModel::TABLE, // for excel table
        ]);

        // Send API Request
        $response = $this->post(route('api.analysis.recogniser.create'), [
            'file_id' => $fileModel->id,
            ...$request->toArray(),
        ]);

        // Check asserts
        $response->assertOk();
        $response->assertJson([
            'user_id' => $fileModel->user_id,
            'file_id' => $fileModel->id,
            'status' => RecogniseStatus::PROCESSED->value,
        ]);
        $this->assertDatabaseHas(RecogniseRequest::class, [
            'user_id' => $fileModel->user_id,
            'file_id' => $fileModel->id,
            'status' => RecogniseStatus::PROCESSED->value,
        ]);
    }
}
