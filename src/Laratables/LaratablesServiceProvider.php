<?php

namespace Ymo\Laratables;

/**
 * This file is part of Laratables,
 * a helper for generating Datatables 1.10+ usable JSON from Eloquent models.
 *
 * @license MIT
 * @package Ymo\Laratables
 */

use Illuminate\Support\ServiceProvider;

class LaratablesServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/config.php' => config_path('laratables.php'),
        ]);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('laratables', function ($app) {
            return $app->make('Ymo\Laratables\Laratables');
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/config.php', 'laratables'
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [ 'datatables' ];
    }
}