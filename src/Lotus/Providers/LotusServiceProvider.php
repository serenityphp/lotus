<?php

namespace Serenity\Lotus\Providers;

use Godruoyi\Snowflake\Snowflake;
use Illuminate\Support\Collection;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Serenity\Lotus\Console\LotusInstallCommand;

class LotusServiceProvider extends ServiceProvider
{
	/**
	 * The commands to be registered.
	 *
	 * @var array
	 */
	protected $devCommands = [
		'LotusInstall' => 'command.lotus.install',
	];

	/**
	 * Register any application services.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bind('VERSION', function (Application $app) {
			return '1.0.1';
		});

		$this->mergeConfigFrom(
			__DIR__ . '/../../config/lotus.php',
			'lotus'
		);

		$this->registerCommands(array_merge(
			$this->devCommands
		));

		$this->rebindLaravelDefaults();
	}

	public function boot()
	{
		$this->registerLaravelAuthModelConfigKey();
		$this->registerMiddleware();
		$this->registerMacros();
		$this->registerProviders();

		$this->publishes([
			__DIR__ . '/../../config/lotus.php' => config_path('lotus.php'),
		]);
	}

	/**
	 * Register the given commands.
	 *
	 * @param  array  $commands
	 * @return void
	 */
	protected function registerCommands(array $commands)
	{
		foreach (array_keys($commands) as $command) {
			call_user_func_array([$this, "register{$command}Command"], []);
		}

		$this->commands(array_values($commands));
	}

	/**
	 * Register various macros for the app.
	 *
	 * @return void
	 */
	protected function registerMacros()
	{
		Collection::macro('then', function ($callback) {
			return $callback($this);
		});

		Collection::macro('pipe', function ($callback) {
			return $this->then($callback);
		});

		Collection::macro('toAssoc', function () {
			return $this->reduce(function ($assoc, $keyAndValue) {
				list($key, $value) = $keyAndValue;
				$assoc[$key] = $value;

				return $assoc;
			}, new static);
		});

		Collection::macro('mapToAssoc', function ($callback) {
			return $this->map($callback)->toAssoc();
		});

		Collection::macro('transpose', function () {
			$items = array_map(function (...$items) {
				return $items;
			}, ...$this->values());

			return new static($items);
		});

		Builder::macro('scope', function ($scope) {
			return $scope->getQuery($this);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerLotusInstallCommand()
	{
		$this->app->singleton('command.lotus.install', function ($app) {
			return new LotusInstallCommand($app['composer'], $app['files']);
		});
	}

	/**
	 * Register our required middleware.
	 *
	 * @return void
	 */
	protected function registerMiddleware()
	{
		if (!is_file(app_path('Domain/Middleware/InertiaMiddleware.php'))) {
			$router = $this->app['router'];
			$router->pushMiddlewareToGroup('web', \Serenity\Lotus\Middleware\InertiaMiddleware::class);
		}

		if (!is_file(app_path('Domain/Middleware/MuteActions.php'))) {
			$router = $this->app['router'];
			$router->pushMiddlewareToGroup('web', \Serenity\Lotus\Middleware\MuteActions::class);
		}
	}

	/**
	 * Register our additional package service providers.
	 *
	 * @return void
	 */
	protected function registerProviders()
	{
		$this->app->singleton(Snowflake::class, function () {
			return (new Snowflake())
				->setStartTimeStamp(time() * 1000)
				->setSequenceResolver(new RandomSequenceResolver(time()));
		});

		$this->app->singleton('breadcrumb', function (Application $app) {
			return $app->make(\Serenity\Lotus\Core\Breadcrumbs::class);
		});
	}

	/**
	 * Various processes within Laravel still need access to
	 * the auth.providers.users.model config key which
	 * has been replaced for Serenity. This resolves it.
	 *
	 * @return void
	 */
	protected function registerLaravelAuthModelConfigKey(): void
	{
		$this->app['config']->set(
			'auth.providers.users.model',
			$this->app['config']->get('auth.providers.users.entity')
		);
	}

	/**
	 * Controllers and Models don't exist in Serenity so
	 * we'll rebind these commands to Actions and Entities
	 * since Laravel squawks if we don't handle it.
	 *
	 * @return void
	 */
	protected function rebindLaravelDefaults(): void
	{
		$this->app->bind(
			'command.controller.make',
			'command.action.make'
		);

		$this->app->bind(
			'command.model.make',
			'command.entity.make'
		);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array_merge(array_values($this->devCommands));
	}
}