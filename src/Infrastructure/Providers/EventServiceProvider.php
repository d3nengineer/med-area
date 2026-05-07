<?php

declare(strict_types=1);

namespace Infrastructure\Providers;

use Domain\AI\Recognise\Events\RecogniseRequestCompleted;
use Domain\Analys\Events\UserAnalysCreated;
use Domain\Analys\Events\UserAnalysDeleted;
use Domain\File\Events\FileMarkedForDeletion;
use Domain\File\Events\FileSoftDeleted;
use Domain\File\Events\FileUploaded;
use Domain\User\Events\UserRegistered;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Infrastructure\Listeners\AI\DispatchUpdateRecogniseRequestJobListener;
use Infrastructure\Listeners\Analys\IndexUserAnalysListener;
use Infrastructure\Listeners\Analys\RemoveUserAnalysFromIndexListener;
use Infrastructure\Listeners\Audit\IndexFileSoftDeletedAuditListener;
use Infrastructure\Listeners\Audit\IndexFileUploadedAuditListener;
use Infrastructure\Listeners\Audit\IndexUserAnalysCreatedAuditListener;
use Infrastructure\Listeners\Audit\IndexUserAnalysDeletedAuditListener;
use Infrastructure\Listeners\File\DispatchDeleteFileJobListener;
use Infrastructure\Listeners\User\SendEmailVerificationListener;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<string, array<int, string>> */
    protected $listen = [
        UserRegistered::class => [SendEmailVerificationListener::class],
        UserAnalysCreated::class => [
            IndexUserAnalysListener::class,
            IndexUserAnalysCreatedAuditListener::class,
        ],
        UserAnalysDeleted::class => [
            RemoveUserAnalysFromIndexListener::class,
            IndexUserAnalysDeletedAuditListener::class,
        ],
        FileUploaded::class => [IndexFileUploadedAuditListener::class],
        FileSoftDeleted::class => [IndexFileSoftDeletedAuditListener::class],

        FileMarkedForDeletion::class => [DispatchDeleteFileJobListener::class],
        RecogniseRequestCompleted::class => [DispatchUpdateRecogniseRequestJobListener::class],
    ];
}
