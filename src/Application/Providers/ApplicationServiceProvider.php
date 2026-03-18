<?php

declare(strict_types=1);

namespace Application\Providers;

use Application\AI\Recogniser\Services\Contracts\RecogniseRequestServiceContract;
use Application\AI\Recogniser\Services\Contracts\RecogniserServiceContract;
use Application\AI\Recogniser\Services\RecogniseRequestService;
use Application\AI\Recogniser\Services\RecogniseResponseParser;
use Application\AI\Recogniser\Services\YVisionOCRService;
use Application\Analys\Services\AnalysService;
use Application\Analys\Services\Contracts\AnalysServiceContract;
use Application\Analys\Services\Contracts\UserAnalysServiceContract;
use Application\Analys\Services\UserAnalysService;
use Application\S3\Services\Contracts\S3ServiceContract;
use Application\S3\Services\YCloudS3Service;
use Application\User\Services\AuthService;
use Application\User\Services\Contracts\AuthServiceContract;
use Tymon\JWTAuth\JWTGuard;
use Application\User\Services\Contracts\RegistrationServiceContract;
use Application\User\Services\Contracts\UserServiceContract;
use Application\User\Services\RegistrationService;
use Application\User\Services\UserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class ApplicationServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [

        // AI
        RecogniserServiceContract::class => YVisionOCRService::class,
        RecogniseRequestServiceContract::class => RecogniseRequestService::class,

        // Files
        S3ServiceContract::class => YCloudS3Service::class,

        // User
        RegistrationServiceContract::class => RegistrationService::class,
        UserServiceContract::class => UserService::class,

        // Analys
        AnalysServiceContract::class => AnalysService::class,
        UserAnalysServiceContract::class => UserAnalysService::class,
    ];

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    public function register(): void
    {
        foreach ($this->bindings as $interface => $class) {
            $this->app->bind($interface, $class);
        }

        $this->app->bind(AuthServiceContract::class, function () {
            /** @var JWTGuard $guard */
            $guard = auth()->guard('api');

            return new AuthService($guard);
        });

        $this->app->singleton(RecogniseResponseParser::class);
    }
}
