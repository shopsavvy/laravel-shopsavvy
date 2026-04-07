<?php

declare(strict_types=1);

namespace ShopSavvy\Laravel;

use Illuminate\Support\ServiceProvider;
use ShopSavvy\Laravel\Commands\PriceCommand;
use ShopSavvy\Laravel\Commands\SearchCommand;
use ShopSavvy\Laravel\Http\Controllers\ShopSavvyController;
use ShopSavvy\Laravel\Views\Components\Price;
use ShopSavvy\Laravel\Views\Components\Search;

class ShopSavvyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/shopsavvy.php', 'shopsavvy');

        $this->app->singleton(ShopSavvyClient::class, function ($app) {
            return new ShopSavvyClient($app['config']['shopsavvy']);
        });

        $this->app->singleton(ShopSavvyManager::class, function ($app) {
            return new ShopSavvyManager($app->make(ShopSavvyClient::class));
        });
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->registerViews();
        $this->registerComponents();
        $this->registerCommands();
        $this->registerRoutes();
    }

    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/shopsavvy.php' => config_path('shopsavvy.php'),
            ], 'shopsavvy-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/shopsavvy'),
            ], 'shopsavvy-views');
        }
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'shopsavvy');
    }

    private function registerComponents(): void
    {
        $this->callAfterResolving(\Illuminate\View\Compilers\BladeCompiler::class, function ($blade) {
            $blade->component('shopsavvy-price', Price::class);
            $blade->component('shopsavvy-search', Search::class);
        });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SearchCommand::class,
                PriceCommand::class,
            ]);
        }
    }

    private function registerRoutes(): void
    {
        if (!$this->app['config']->get('shopsavvy.routes.enabled', false)) {
            return;
        }

        $prefix     = $this->app['config']->get('shopsavvy.routes.prefix', 'api/shopsavvy');
        $middleware = $this->app['config']->get('shopsavvy.routes.middleware', ['api']);

        $this->app['router']
            ->prefix($prefix)
            ->middleware($middleware)
            ->group(__DIR__ . '/routes.php');
    }
}
