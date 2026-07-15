<?php

namespace Modules\Tax\Providers;

use Modules\Tax\Contracts\HolidayProviderInterface;
use Modules\Tax\Contracts\TaxAutomationServiceInterface;
use Modules\Tax\Support\Automation\NullTaxAutomationService;
use Modules\Tax\Support\DatabaseHolidayProvider;
use Modules\Tax\Support\TaxGeneratorRegistry;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class TaxServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Tax';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'tax';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(TaxGeneratorRegistry::class);

        // Default holiday source: the tax_holidays table (admin-editable
        // without a deploy). Swap this binding to change the source later.
        $this->app->bind(HolidayProviderInterface::class, DatabaseHolidayProvider::class);

        // No-op default — proves the generation engine is decoupled from
        // automation. A real gov-portal integration later swaps this binding.
        $this->app->singleton(TaxAutomationServiceInterface::class, NullTaxAutomationService::class);
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
