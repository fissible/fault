<?php

declare(strict_types=1);

namespace Fissible\Fault\Providers;

use Fissible\Fault\Http\Controllers\FaultController;
use Fissible\Fault\Models\FaultGroup;
use Fissible\Fault\Services\FaultReporter;
use Fissible\Fault\Services\TestStubGenerator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FaultServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/fault.php', 'fault');

        $this->app->singleton(FaultReporter::class);
        $this->app->singleton(TestStubGenerator::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'fault');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/fault.php' => config_path('fault.php'),
            ], 'fault-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'fault-migrations');
        }

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        $prefix     = config('watch.prefix', 'watch');
        $middleware = ['web'];

        Route::prefix($prefix . '/faults')
            ->name('watch.faults')
            ->middleware($middleware)
            ->group(function () {
                Route::get('/', [FaultController::class, 'index'])->name('');
                Route::get('/{faultGroup}', [FaultController::class, 'show'])->name('.show');
                Route::patch('/{faultGroup}/status', [FaultController::class, 'updateStatus'])->name('.status');
                Route::patch('/{faultGroup}/notes', [FaultController::class, 'saveNotes'])->name('.notes');
                Route::post('/{faultGroup}/test', [FaultController::class, 'generateTest'])->name('.test');
                Route::delete('/{faultGroup}', [FaultController::class, 'delete'])->name('.delete');
            });
    }
}
