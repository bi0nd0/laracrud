<?php namespace Whitegolem\LaraCrud;

use Illuminate\Support\ServiceProvider;

class CrudServiceProvider extends ServiceProvider {

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
		$this->package('whitegolem/laracrud');
		
		include __DIR__.'/../../filters.php'; //to load a filters file for my package
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['laracrud'] = $this->app->share(function($app) {
			return new CrudController;
		});

		
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}