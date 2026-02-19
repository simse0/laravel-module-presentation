<?php

namespace Trafficdesign\Presentation;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Trafficdesign\Presentation\Contracts\AuthorizerInterface;
use Trafficdesign\Presentation\Contracts\DataCollectorInterface;
use Trafficdesign\Presentation\Contracts\SlideBuilderInterface;

class PresentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/presentation.php', 'presentation');

        $this->app->singleton(PresentationEngine::class, function ($app) {
            return new PresentationEngine(
                $app->make(SlideBuilderInterface::class),
                $app->make(DataCollectorInterface::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'presentation');
        $this->loadRoutesFrom(__DIR__ . '/../routes/presentation.php');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components/presentation', 'presentation');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\InstallCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/presentation.php' => config_path('presentation.php'),
            ], 'presentation-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/presentation'),
            ], 'presentation-views');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'presentation-migrations');
        }
    }
}
