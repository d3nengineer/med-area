<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation\Analys\Controllers;

use Domain\Analys\DTO\Filters\FilterUserAnalysDTO;
use Application\Analys\DTO\Requests\CreateUserAnalysisRequestDTO;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;
use Domain\Analys\Events\UserAnalysCreated;
use Domain\Analys\Events\UserAnalysDeleted;
use Domain\Analys\Factories\UserAnalysFactory;
use Domain\Analys\Models\UserAnalys;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class UserAnalysControllerTest extends TestCase
{
    public function test_user_analysis_create_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $factory = new UserAnalysFactory();
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id])),
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id])),
            ],
        ]);

        // Check that records missing in DB
        foreach ($dto->analysis as $analys) {
            $this->assertDatabaseMissing(UserAnalys::class, $analys->toArray());
        }

        // Send API Request
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), $dto->toArray());

        // Check asserts
        $response->assertCreated();

        // Check that records in DB
        foreach ($dto->analysis as $analys) {
            $this->assertDatabaseHas(UserAnalys::class, $analys->toArray());
        }
    }

    public function test_user_analysis_create_dispatches_auditable_event_per_saved_analysis_without_changing_response_code(): void
    {
        Event::fake();

        $user = $this->authUser();
        $factory = new UserAnalysFactory();
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id])),
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user->id])),
            ],
        ]);

        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), $dto->toArray());

        $response->assertCreated();
        Event::assertDispatchedTimes(UserAnalysCreated::class, count($dto->analysis));
    }

    public function test_user_analysis_create_success_with_collection_payload(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Send API Request using the Postman collection payload shape
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), [
            'user_id' => $user->id,
            'analysis' => [[
                'user_id' => $user->id,
                'analys_id' => Analys::D3->value,
                'data' => 4.8,
                'unit' => 'mmol/L',
            ]],
        ]);

        // Check asserts
        $response->assertCreated();
        $response->assertJsonCount(1, 'data');
        $this->assertDatabaseHas(UserAnalys::class, [
            'user_id' => $user->id,
            'analys_id' => Analys::D3->value,
            'data' => 4.8,
            'unit' => 'mmol/L',
        ]);
    }

    public function test_user_analysis_create_validation_unauth(): void
    {
        // Auth user for testing
        $user = $this->getUser();

        // Send API Request
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]));

        // Check asserts
        $response->assertUnauthorized();
    }

    public function test_user_analysis_create_validation_missing_values(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $userAnalysDTO = UserAnalysDTO::from(['analys_name' => '']);
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [$userAnalysDTO, $userAnalysDTO],
        ]);

        // Send API Request
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), $dto->toArray());

        // Check asserts
        $response->assertUnprocessable();
        $response->assertInvalid(['analysis.0.user_id', 'analysis.0.analys_id', 'analysis.0.data']);

        // Check that records missing in DB
        foreach ($dto->analysis as $analys) {
            $this->assertDatabaseMissing(UserAnalys::class, $analys->toArray());
        }
    }

    public function test_user_analysis_create_validation_errors(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $userAnalysDTO = UserAnalysDTO::from([
            'user_id' => $user->id, // skip 403 errors
        ]);
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [$userAnalysDTO],
        ]);

        // Send API Request
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), array_merge(
            $dto->toArray(),
            [
                'data' => 'str1ng1', // string, must be numeric
                'analys_id' => 0, // unreal analys id
            ]
        ));

        // Check asserts
        $response->assertUnprocessable();
        $response->assertInvalid(['analysis.0.analys_id', 'analysis.0.data']);

        // Check that records missing in DB
        foreach ($dto->analysis as $analys) {
            $this->assertDatabaseMissing(UserAnalys::class, $analys->toArray());
        }
    }

    public function test_user_analysis_create_validation_invalid_unit(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Send API Request with unsupported unit from API client payload
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), [
            'user_id' => $user->id,
            'analysis' => [[
                'user_id' => $user->id,
                'analys_id' => \Domain\Analys\Enums\Analys::D3->value,
                'data' => 4.8,
                'unit' => 'mg/dL',
            ]],
        ]);

        // Check asserts
        $response->assertUnprocessable();
        $response->assertInvalid(['analysis.0.unit']);
    }

    public function test_user_analysis_create_validation_unreal_user_id(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $factory = new UserAnalysFactory();
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => fake()->uuid()])),
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => fake()->uuid()])),
            ],
        ]);

        // Send API Request
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user->id]), $dto->toArray());

        // Check asserts
        $response->assertUnprocessable();
        $response->assertInvalid(['analysis.0.user_id', 'analysis.1.user_id']);
        $response->assertJsonValidationErrors([
            'analysis.0.user_id' => [
                'The analysis.0.user_id must match the authenticated user ID.',
                'The analysis.0.user_id must match in the url user ID.',
            ],
        ]);

        // Check that records missing in DB
        foreach ($dto->analysis as $analys) {
            $this->assertDatabaseMissing(UserAnalys::class, $analys->toArray());
        }
    }

    public function test_user_analysis_create_forbidden(): void
    {
        // Auth user for testing
        $user = $this->authUser();
        $user2 = $this->getUser();

        // Data for testing
        $factory = new UserAnalysFactory();
        $dto = CreateUserAnalysisRequestDTO::from([
            'analysis' => [
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user2->id])), // using another user id
                UserAnalysDTO::from(array_merge($factory->definition(), ['user_id' => $user2->id])), // using another user id
            ],
        ]);

        // Send API Request
        $response = $this->post(route('api.users.analysis.create', ['userId' => $user2->id]), $dto->toArray());

        // Check asserts
        $response->assertForbidden();

        // Check that records missing in DB
        foreach ($dto->analysis as $analys) {
            $this->assertDatabaseMissing(UserAnalys::class, $analys->toArray());
        }
    }

    public function test_user_analysis_index_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $count = 3;
        $factory = new UserAnalysFactory();

        // Data with another values for testing filters
        UserAnalys::factory(10)
            ->for($user)
            ->createMany();

        // Delete analys_id for testing filters
        UserAnalys::query()->whereAnalysId(Analys::D3->value)->delete();

        // Data UserAnalys Models for searching with filters (user_id, analys_id)
        for ($i = 0; $i < $count; $i++) {
            UserAnalys::query()->create(array_merge($factory->definition(), [
                'user_id' => $user->id,
                'analys_id' => Analys::D3,
            ]));
        }

        // Filters for testing
        $filters = FilterUserAnalysDTO::from([
            'user_ids' => [$user->id],
            'analys_ids' => [Analys::D3],
        ]);

        // Send API Request with filters
        $response = $this->get(route('api.users.analysis.index', array_merge([
            'userId' => $user->id,
        ], $filters->toArray())));

        // Check asserts
        $response->assertOk();
        $response->assertJsonCount($count, 'data');
    }

    public function test_user_analysis_index_forbidden_unreal_user_id(): void
    {
        // Auth user for testing
        $user = $this->authUser();
        $user2 = $this->getUser();

        // Filters for testing
        $filters = FilterUserAnalysDTO::from(['user_ids' => [$user->id]]);

        // Send API Request with filters and another user_id in url
        $response = $this->get(route('api.users.analysis.index', array_merge([
            'userId' => $user2->id,
        ], $filters->toArray())));

        // Check assert forbidden, 403 http code
        $response->assertForbidden();
    }

    public function test_user_analysis_index_validation_filter_user_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();
        $user2 = $this->getUser();

        // Filters with another user_id for testing
        $filters = FilterUserAnalysDTO::from(['user_ids' => [$user->id, $user2->id]]);

        // Send API Request with bad filters
        $response = $this->get(route('api.users.analysis.index', array_merge([
            'userId' => $user->id,
        ], $filters->toArray())));

        // Check asserts user_ids filter errors
        $response->assertUnprocessable();
        $response->assertInvalid(['user_ids.1']);
    }

    public function test_user_analysis_destroy_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Data for testing
        $count = 3;
        $factory = new UserAnalysFactory();

        // Data with another values for testing filters
        UserAnalys::factory(10)
            ->for($user)
            ->createMany();

        // Delete analys_id for testing filters
        UserAnalys::query()->whereAnalysId(Analys::D3->value)->delete();
        $countSaveAnalysis = UserAnalys::query()->get()->count();

        // Data UserAnalys Models for searching with filters (user_id, analys_id)
        $analysisForDeleting = [];
        for ($i = 0; $i < $count; $i++) {
            $analysisForDeleting[] = UserAnalys::query()->create(array_merge($factory->definition(), [
                'user_id' => $user->id,
                'analys_id' => Analys::D3->value,
            ]));
        }

        // Filters for testing
        $filters = FilterUserAnalysDTO::from([
            'user_ids' => [$user->id],
            'analys_ids' => [Analys::D3],
        ]);

        // Check assert that analysis saved
        foreach ($analysisForDeleting as $analys) {
            $this->assertDatabaseHas(UserAnalys::class, [
                'user_id' => $analys->user_id,
                'analys_id' => $analys->analys_id,
            ]);
        }

        // Send API Request with filters
        $response = $this->delete(route('api.users.analysis.destroy', array_merge([
            'userId' => $user->id,
        ], $filters->toArray())));

        // Check assert success response. HTTP code 204
        $response->assertNoContent();

        // Check assert that analysis not deleting, because filtering
        $this->assertDatabaseCount(UserAnalys::class, $countSaveAnalysis);

        // Check assert that analysis deleted
        foreach ($analysisForDeleting as $analys) {
            $this->assertDatabaseMissing(UserAnalys::class, [
                'user_id' => $analys->user_id,
                'analys_id' => $analys->analys_id,
            ]);
        }
    }

    public function test_user_analysis_destroy_dispatches_auditable_event_per_deleted_analysis_without_changing_status_code(): void
    {
        Event::fake();

        $user = $this->authUser();
        $factory = new UserAnalysFactory();
        $deletedAnalyses = [];

        for ($i = 0; $i < 2; $i++) {
            $deletedAnalyses[] = UserAnalys::query()->create(array_merge($factory->definition(), [
                'user_id' => $user->id,
                'analys_id' => Analys::D3->value,
            ]));
        }

        $response = $this->delete(route('api.users.analysis.destroy', [
            'userId' => $user->id,
            'user_ids' => [$user->id],
            'analys_ids' => [Analys::D3->value],
        ]));

        $response->assertNoContent();
        Event::assertDispatchedTimes(UserAnalysDeleted::class, count($deletedAnalyses));
    }

    public function test_user_analysis_destroy_forbidden_unreal_user_id(): void
    {
        // Auth user for testing
        $user = $this->authUser();
        $user2 = $this->getUser();

        // Filters for testing
        $filters = FilterUserAnalysDTO::from(['user_ids' => [$user->id]]);

        // Send API Request with filters and another user_id in url
        $response = $this->delete(route('api.users.analysis.destroy', array_merge([
            'userId' => $user2->id,
        ], $filters->toArray())));

        // Check assert forbidden, 403 http code
        $response->assertForbidden();
    }

    public function test_user_analysis_destroy_validation_filter_user_ids(): void
    {
        // Auth user for testing
        $user = $this->authUser();
        $user2 = $this->getUser();

        // Filters with another user_id for testing
        $filters = FilterUserAnalysDTO::from(['user_ids' => [$user->id, $user2->id]]);

        // Send API Request with bad filters
        $response = $this->delete(route('api.users.analysis.destroy', array_merge([
            'userId' => $user->id,
        ], $filters->toArray())));

        // Check asserts user_ids filter errors
        $response->assertUnprocessable();
        $response->assertInvalid(['user_ids.1']);
    }
}
