<?php

declare(strict_types=1);

namespace Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Domain\AI\Recognise\Repositories\RecogniseRequestRepositoryContract;
use Domain\Analys\Repositories\AnalysRepositoryContract;
use Domain\Analys\Repositories\UserAnalysRepositoryContract;
use Domain\Analys\Repositories\UserAnalysSearchRepositoryContract;
use Domain\File\Repositories\FileRepositoryContract;
use Domain\User\Repositories\UserRepositoryContract;
use Infrastructure\Repositories\AnalysRepository;
use Infrastructure\Repositories\FileRepository;
use Infrastructure\Repositories\RecogniseRequestRepository;
use Infrastructure\Repositories\UserAnalysRepository;
use Infrastructure\Repositories\UserAnalysSearchRepository;
use Infrastructure\Repositories\UserRepository;
use Infrastructure\Services\AnalysSearchIndexService;
use Infrastructure\Services\Contracts\AnalysSearchIndexServiceContract;
use Infrastructure\Services\Contracts\ElasticsearchClientServiceContract;
use Infrastructure\Services\Contracts\UserActivityAuditIndexServiceContract;
use Infrastructure\Services\ElasticsearchClientService;
use Infrastructure\Services\UserActivityAuditIndexService;

class InfrastructureServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [

        // AI
        RecogniseRequestRepositoryContract::class => RecogniseRequestRepository::class,

        // File
        FileRepositoryContract::class => FileRepository::class,

        // User
        UserRepositoryContract::class => UserRepository::class,

        // Analys
        AnalysRepositoryContract::class => AnalysRepository::class,
        UserAnalysRepositoryContract::class => UserAnalysRepository::class,
        UserAnalysSearchRepositoryContract::class => UserAnalysSearchRepository::class,
        AnalysSearchIndexServiceContract::class => AnalysSearchIndexService::class,
        UserActivityAuditIndexServiceContract::class => UserActivityAuditIndexService::class,
    ];

    public function register(): void
    {
        foreach ($this->bindings as $interface => $class) {
            $this->app->bind($interface, $class);
        }

        $this->app->singleton(ElasticsearchClientServiceContract::class, ElasticsearchClientService::class);
    }

    public function boot(): void {}
}
