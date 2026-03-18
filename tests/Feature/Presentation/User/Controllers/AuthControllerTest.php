<?php

declare(strict_types=1);

namespace Tests\Feature\Presentation\User\Controllers;

use Domain\User\DTO\UserDTO;
use Domain\User\Factories\UserFactory;
use Domain\User\Models\User;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_login_success(): void
    {
        // Data for testing
        $userData = UserDTO::from(new UserFactory()->definition());
        $userData->password = $this->userPassword;

        // User for testing
        User::create($userData->toArray());

        // Send API Request
        $response = $this->post(route('api.auth.login'), $userData->only('nickname', 'password')->toArray());

        // Check asserts
        $response->assertOk();
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
    }

    public function test_login_validation(): void
    {
        // Data for testing
        $userData = UserDTO::from(new UserFactory()->definition());

        // User for testing
        User::create($userData->toArray());

        // Send API Request with empty data
        $response = $this->post(route('api.auth.login'), []);

        // Check asserts
        $response->assertUnprocessable();
        $response->assertClientError();
        $response->assertInvalid(['nickname', 'password']);
    }

    public function test_login_bad_nickname(): void
    {
        // Data for testing
        $userData = UserDTO::from(new UserFactory()->definition());
        $userData->password = $this->userPassword;

        // User for testing
        User::create($userData->toArray());

        // Data for request
        $data = [
            'nickname' => fake()->userName(), // false value
            'password' => $userData->password, // true value
        ];

        // Send API Request
        $response = $this->post(route('api.auth.login'), $data);

        // Check asserts
        $response->assertBadRequest();
        $response->assertClientError();
        $response->assertJson(['message' => 'Nickname or password incorrect.']);
    }

    public function test_login_bad_password(): void
    {
        // Data for testing
        $userData = UserDTO::from(new UserFactory()->definition());
        $userData->password = $this->userPassword;

        // User for testing
        User::create($userData->toArray());

        // Data for request
        $data = [
            'nickname' => $userData->nickname, // true value
            'password' => fake()->password(), // false value
        ];

        // Send API Request
        $response = $this->post(route('api.auth.login'), $data);

        // Check asserts
        $response->assertBadRequest();
        $response->assertClientError();
        $response->assertJson(['message' => 'Nickname or password incorrect.']);
    }

    public function test_refresh_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Send API Request
        $response = $this->actingAs($user)->post(route('api.auth.refresh'));

        // Check asserts
        $response->assertOk();
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
    }

    public function test_refresh_unauth(): void
    {
        // Send API Request
        $response = $this->post(route('api.auth.refresh'));

        // Check asserts
        $response->assertUnauthorized();
    }

    public function test_logout_success(): void
    {
        // Auth user for testing
        $user = $this->authUser();

        // Send API Request
        $response = $this->actingAs($user)->post(route('api.auth.logout'));

        // Check asserts
        $response->assertNoContent();
    }

    public function test_logout_unauth(): void
    {
        // Send API Request
        $response = $this->post(route('api.auth.logout'));

        // Check asserts
        $response->assertUnauthorized();
    }

    public function test_refresh_issues_new_token_with_different_claims(): void
    {
        // Get user
        $user = $this->authUser();

        // Login for get access token
        $loginResponse = $this->post(route('api.auth.login'), [
            'nickname' => $user->nickname,
            'password' => $this->userPassword,
        ]);
        $loginResponse->assertOk();
        $oldToken = $loginResponse->json('access_token');

        // Refresh using old token
        $refreshResponse = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->post(route('api.auth.refresh'));
        $refreshResponse->assertOk();
        $newToken = $refreshResponse->json('access_token');

        // A distinct token must be issued (resetClaims=true produces a new jti)
        $this->assertNotEquals($oldToken, $newToken, 'Refresh must issue a new token, not reuse the old one');

        // New token should be valid
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->post(route('api.auth.logout'))
            ->assertNoContent();
    }
}
