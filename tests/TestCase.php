<?php

declare(strict_types=1);

namespace Tests;

use Domain\User\DTO\UserDTO;
use Domain\User\Factories\UserFactory;
use Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = \Database\Seeders\TestSeeder::class;

    protected string $userPassword = 'GbUTPsq894b!fM1';

    public function setUp(): void
    {
        parent::setUp();

        $this->withHeader('accept', 'application/json');
    }

    /**
     * Get User and Authenticate
     *
     * @return User
     */
    public function authUser(): User
    {
        $user = $this->getUser();

        auth()->login($user);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Get User model with verified email
     *
     * @return User
     */
    protected function getUser(): User
    {
        $userDTO = UserDTO::from(new UserFactory()->definition());
        $userDTO->password = $this->userPassword;

        /** @var User $userModel */
        $userModel = User::create($userDTO->toArray());

        $userModel->markEmailAsVerified();

        return $userModel;
    }
}
