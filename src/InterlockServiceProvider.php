<?php

declare(strict_types=1);

namespace Drivkraft\Interlock;

use Drivkraft\Interlock\Console\Database\SeedCommand;
use Drivkraft\Interlock\Console\Make;
use Drivkraft\Interlock\Console\Make\MakeMigration;
use Drivkraft\Interlock\Console\MakeModuleCommand;
use Drivkraft\Interlock\Console\ModuleCacheCommand;
use Drivkraft\Interlock\Console\ModuleClearCommand;
use Drivkraft\Interlock\Console\ModuleListCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Livewire\Livewire;

class InterlockServiceProvider extends ServiceProvider
{
    /**
     * The module:* generators, one per Laravel make:* command.
     *
     * @var list<class-string>
     */
    protected array $generators = [
        Make\MakeAction::class,
        Make\MakeCast::class,
        Make\MakeChannel::class,
        Make\MakeClass::class,
        Make\MakeCommand::class,
        Make\MakeComponent::class,
        Make\MakeController::class,
        Make\MakeData::class,
        Make\MakeEnum::class,
        Make\MakeEvent::class,
        Make\MakeException::class,
        Make\MakeFactory::class,
        Make\MakeInterface::class,
        Make\MakeJob::class,
        Make\MakeJobMiddleware::class,
        Make\MakeListener::class,
        Make\MakeMail::class,
        Make\MakeMiddleware::class,
        MakeMigration::class,
        Make\MakeModel::class,
        Make\MakeNotification::class,
        Make\MakeObserver::class,
        Make\MakePolicy::class,
        Make\MakeProvider::class,
        Make\MakeRequest::class,
        Make\MakeResource::class,
        Make\MakeRule::class,
        Make\MakeScope::class,
        Make\MakeSeeder::class,
        Make\MakeTest::class,
        Make\MakeTrait::class,
        SeedCommand::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/interlock.php', 'interlock');

        $this->app->singleton(ModuleManifest::class, fn (): ModuleManifest => new ModuleManifest(
            new Filesystem,
            $this->app->make('config')->get('interlock'),
        ));

        $this->app->singleton(ModuleRegistry::class);

        foreach ($this->manifest()->get('providers') as $provider) {
            $this->app->register($provider);
        }

        $this->app->singleton(MakeMigration::class, fn (Application $app): MakeMigration => new MakeMigration($app->make('migration.creator'), $app->make('composer')));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/interlock.php' => config_path('interlock.php'),
            ], ['interlock', 'interlock-config']);

            $this->optimizes(optimize: 'module:cache', clear: 'module:clear', key: 'modules');

            $this->commands([
                MakeModuleCommand::class,
                ModuleCacheCommand::class,
                ModuleClearCommand::class,
                ModuleListCommand::class,
                ...$this->generators,
                ...(class_exists(Livewire::class) ? [Make\MakeLivewire::class] : []),
                ...$this->manifest()->get('commands'),
            ]);
        }

        $this->bootRoutes();
        $this->bootViews();
        $this->bootTranslations();
        $this->bootComponents();
        $this->bootLivewire();
        $this->bootEvents();

        $this->loadMigrationsFrom($this->manifest()->get('migrations'));

        if ($this->app->make('config')->get('interlock.discover.factories')) {
            (new FactoryResolver($this->app->make(ModuleRegistry::class), $this->app->getNamespace()))->register();
        }
    }

    protected function manifest(): ModuleManifest
    {
        return $this->app->make(ModuleManifest::class);
    }

    /**
     * Files named in interlock.routes get that group's attributes; others are loaded as-is.
     */
    protected function bootRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $groups = $this->app->make('config')->get('interlock.routes', []);

        foreach ($this->manifest()->get('routes') as $route) {
            Route::group($groups[$route['group']] ?? [], $route['path']);
        }
    }

    protected function bootViews(): void
    {
        foreach ($this->manifest()->get('views') as $name => $path) {
            $this->loadViewsFrom($path, $name);
        }
    }

    protected function bootTranslations(): void
    {
        foreach ($this->manifest()->get('translations') as $name => $path) {
            $this->loadTranslationsFrom($path, $name);
            $this->loadJsonTranslationsFrom($path);
        }
    }

    protected function bootComponents(): void
    {
        $components = $this->manifest()->get('components');

        $this->callAfterResolving(BladeCompiler::class, function (BladeCompiler $blade) use ($components): void {
            foreach ($components as $prefix => $namespace) {
                $blade->componentNamespace($namespace, $prefix);
            }
        });
    }

    /**
     * Registers {module}:: and {module}.pages:: (one per interlock.livewire.namespaces entry) as Livewire
     * namespaces, so <livewire:shop::cart /> and Route::livewire('/orders', 'shop.pages::orders')
     * resolve to the module. Existing sub-namespace directories also become view and anonymous
     * component namespaces, so <x-shop.layouts::app> works.
     */
    protected function bootLivewire(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        foreach ($this->manifest()->get('livewire') as $namespace => $livewire) {
            Livewire::addNamespace($namespace, ...$livewire['paths']);

            if (! $livewire['views'] || ! is_dir($livewire['paths']['viewPath'])) {
                continue;
            }

            $this->callAfterResolving('blade.compiler', fn (BladeCompiler $blade) => $blade->anonymousComponentPath($livewire['paths']['viewPath'], $namespace));
            $this->callAfterResolving('view', fn (Factory $view) => $view->addNamespace($namespace, $livewire['paths']['viewPath']));
        }
    }

    protected function bootEvents(): void
    {
        $events = $this->app->make('events');

        foreach ($this->manifest()->get('events') as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                $events->listen($event, $listener);
            }
        }
    }
}
