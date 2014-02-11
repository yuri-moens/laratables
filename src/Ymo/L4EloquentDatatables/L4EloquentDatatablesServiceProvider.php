<?php namespace Ymo\L4EloquentDatatables;

use Illuminate\Support\ServiceProvider;

class L4EloquentDatatablesServiceProvider extends ServiceProvider {

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
	        $this->package('ymo/l4-eloquent-datatables');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
	    $this->app['datatables'] = $this->app->share(function($app)
	    {
	        return new L4EloquentDatatables;
	    });
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
	        return array('datatables');
	}

}