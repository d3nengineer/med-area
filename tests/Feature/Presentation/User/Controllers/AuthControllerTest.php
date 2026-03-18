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
        // User for testing
        $user = $this->getUser();

        // Data for request
        $data = [
            'nickname' => $user->nickname, // correct nickname
            'password' => $this->userPassword, // correct password
        ];

        // Send API Request
        $response = $this->post(route('api.auth.login'), $data);

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
        // User for testing
        $this->getUser();

        // Send API Request with empty data
        $response = $this->post(route('api.auth.login'), []);

        // Check asserts
        $response->assertUnprocessable();
        $response->assertClientError();
        $response->assertInvalid(['nickname', 'password']);
    }

    public function test_login_bad_nickname(): void
    {
        // User for testing
        $this->getUser();

        // Data for request
        $data = [
            'nickname' => '123', // bad nickname
            'password' => $this->userPassword, // correct password
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
        // User for testing
        $user = $this->getUser();

        // Data for request
        $data = [
            'nickname' => $user->nickname, // correct nickname
            'password' => fake()->password(), // bad password
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
