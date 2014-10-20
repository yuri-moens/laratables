<?php

namespace Ymo\Laratables;

use Illuminate\Support\ServiceProvider;

class LaratablesServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('ymo/laratables');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app['laratables'] = $this->app->share(function ($app) {
            return new Laratables;
        });
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