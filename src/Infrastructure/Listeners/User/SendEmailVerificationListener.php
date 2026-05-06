<?php

declare(strict_types=1);

namespace Infrastructure\Listeners\User;

use Domain\User\Events\UserRegistered;
use Domain\User\Models\User;
use Infrastructure\Notifications\User\EmailVerificationNotification;

class SendEmailVerificationListener
{
    public function handle(UserRegistered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if (! $user->hasVerifiedEmail()) {
            $user->notify(new EmailVerificationNotification($user));
        }
    }
}
