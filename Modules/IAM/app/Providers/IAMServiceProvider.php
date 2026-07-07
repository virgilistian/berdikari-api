<?php

namespace Modules\IAM\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\IAM\Http\Middleware\SetPermissionsTeamId;
use Nwidart\Modules\Support\ModuleServiceProvider;

class IAMServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'IAM';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'iam';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Register the middleware alias so routes can use 'permission.team'.
     */
    public function boot(): void
    {
        parent::boot();

        /** @var \Illuminate\Routing\Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('permission.team', SetPermissionsTeamId::class);
    }
}
