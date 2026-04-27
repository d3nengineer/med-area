<?php

declare(strict_types=1);

namespace Application\User\Services;

use Domain\User\DTO\UserDTO;
use Application\User\Services\Contracts\RegistrationServiceContract;
use Domain\User\Events\UserRegistered;
use Domain\User\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Domain\User\Repositories\UserRepositoryContract;

class RegistrationService implements RegistrationServiceContract
{
    public function __construct(
        protected readonly UserRepositoryContract $userRepository,
    ) {}

    /**
     * Create new User model
     * Send email message for confirmation of registration
     *
     * @param UserDTO $userDTO
     * @return UserDTO
     *
     * @throws ModelNotFoundException
     */
    public function register(UserDTO $userDTO): UserDTO
    {
        // Create new User model
        $userDTO = UserDTO::from($this->userRepository->create($userDTO));

        try {
            // Send email message for confirmation of registration
            $userModel = User::whereId($userDTO->id)->firstOrFail();

            event(new UserRegistered($userModel));
        } catch (ModelNotFoundException $e) {
            logger()->critical('[RegistrationService.register] user not found after creation, cannot trigger UserRegistered event', [
                'error'   => $e->getMessage(),
                'context' => ['user_id' => $userDTO->id],
            ]);
            throw $e;
        }

        return $userDTO;
    }

    /**
     * Send email message for confirmation of registration
     *
     * @param User $user
     * @return void
     */
    public function sendEmailVerificationNotification(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            event(new UserRegistered($user));
        }
    }
}
