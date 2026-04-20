<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation\Analys;

use Application\Analys\Services\Contracts\UserAnalysSearchServiceContract;
use Domain\Analys\DTO\UserAnalysDTO;
use Domain\Analys\Enums\Analys;
use Mockery\Expectation;
use Tests\TestCase;

class UserAnalysSearchTest extends TestCase
{
    public function test_authenticated_user_can_search_analyses(): void
    {
        $user = $this->authUser();

        $mockResults = collect([
            UserAnalysDTO::from(['id' => Analys::D3->value, 'analys_name' => strtolower(Analys::D3->name), 'user_id' => $user->id, 'data' => 5.5]),
        ]);

        /** @var Expectation $expectation */
        $expectation = $this->mock(UserAnalysSearchServiceContract::class)
            ->shouldReceive('search');
        $expectation->once()->andReturn($mockResults);

        $response = $this->get(route('api.users.analysis.search', ['userId' => $user->id, 'q' => Analys::D3->name]));

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_search_returns_422_when_q_param_missing(): void
    {
        $user = $this->authUser();

        $response = $this->get(route('api.users.analysis.search', ['userId' => $user->id]));

        $response->assertUnprocessable();
    }

    public function test_search_returns_422_when_q_is_too_short(): void
    {
        $user = $this->authUser();

        $response = $this->get(route('api.users.analysis.search', ['userId' => $user->id, 'q' => 'a']));

        $response->assertUnprocessable();
    }

    public function test_search_returns_401_when_unauthenticated(): void
    {
        $user = $this->getUser();

        $response = $this->get(route('api.users.analysis.search', ['userId' => $user->id, 'q' => Analys::D3->name]));

        $response->assertUnauthorized();
    }

    public function test_user_cannot_search_another_users_analyses(): void
    {
        $this->authUser();
        $otherUser = $this->getUser();

        $response = $this->get(route('api.users.analysis.search', ['userId' => $otherUser->id, 'q' => Analys::D3->name]));

        $response->assertForbidden();
    }
}
