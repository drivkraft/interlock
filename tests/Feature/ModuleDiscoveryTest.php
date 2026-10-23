<?php

declare(strict_types=1);

use Drivkraft\Interlock\Facades\Interlock;
use Drivkraft\Interlock\InterlockServiceProvider;
use Drivkraft\Interlock\Module;
use Drivkraft\Interlock\ModuleManifest;
use Drivkraft\Interlock\ModuleRegistry;
use Fixture\Blog\Database\Factories\PostFactory;
use Fixture\Blog\Events\PostPublished;
use Fixture\Blog\Models\Post;
use Fixture\Blog\Providers\BlogServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->cachePath = storage_path('framework/testing/modules-'.Str::random(8).'.php');

    config([
        'interlock.path' => fixturesPath(),
        'interlock.cache' => $this->cachePath,
        'interlock.routes.tenant' => ['middleware' => ['fixture-tenant'], 'prefix' => 'workspace'],
    ]);

    $this->app->forgetInstance(ModuleManifest::class);
    $this->app->forgetInstance(ModuleRegistry::class);
    $this->app->register(InterlockServiceProvider::class, force: true);

    Route::getRoutes()->refreshNameLookups();
});

afterEach(function (): void {
    File::delete($this->cachePath);
});

it('merges the package config', function (): void {
    expect(config('interlock.namespace'))->toBe('Module')
        ->and(config('interlock.source'))->toBe('src')
        ->and(config('interlock.discover.providers'))->toBeTrue();
});

it('publishes the config under the interlock tags', function (string $tag): void {
    $paths = ServiceProvider::pathsToPublish(InterlockServiceProvider::class, $tag);

    expect($paths)->toHaveCount(1)
        ->and(array_values($paths))->toBe([config_path('interlock.php')]);
})->with(['interlock', 'interlock-config']);

it('discovers modules with the namespace from their composer.json', function (): void {
    $module = app(ModuleRegistry::class)->module('blog');

    expect($module->namespace)->toBe('Fixture\\Blog\\')
        ->and($module->sourcePath())->toBe(fixturesPath('blog/src'))
        ->and(app(ModuleRegistry::class)->moduleForClass(Post::class)?->name)->toBe('blog')
        ->and(app(ModuleRegistry::class)->moduleForPath(fixturesPath('blog/src/Models/Post.php'))?->name)->toBe('blog');
});

it('exposes the registry through the Modules facade', function (): void {
    expect(Interlock::module('blog'))->toBeInstanceOf(Module::class)
        ->and(Interlock::modules()->keys()->all())->toBe(['blog'])
        ->and(Interlock::path())->toBe(fixturesPath());
});

it('registers module service providers', function (): void {
    expect(app()->providerIsLoaded(BlogServiceProvider::class))->toBeTrue()
        ->and(app('fixture.blog.registered'))->toBeTrue();
});

it('loads web and api route files inside their configured groups', function (): void {
    $web = Route::getRoutes()->getByName('fixture.blog.web');
    $api = Route::getRoutes()->getByName('fixture.blog.api');

    expect($web->middleware())->toBe(['web'])
        ->and($web->uri())->toBe('blog')
        ->and($api->middleware())->toBe(['api'])
        ->and($api->uri())->toBe('api/posts');
});

it('applies extra route groups from config and loads unmapped route files as-is', function (): void {
    $tenant = Route::getRoutes()->getByName('fixture.blog.tenant');
    $raw = Route::getRoutes()->getByName('fixture.blog.raw');

    expect($tenant->middleware())->toBe(['fixture-tenant'])
        ->and($tenant->uri())->toBe('workspace/tenant-blog')
        ->and($raw->middleware())->toBe([])
        ->and($raw->uri())->toBe('raw-blog');
});

it('registers the module view namespace', function (): void {
    expect(view('blog::index')->render())->toContain('blog index');
});

it('registers module translations and json translations', function (): void {
    app()->setLocale('en');

    expect(__('blog::messages.hello'))->toBe('Hello from blog')
        ->and(__('Read more'))->toBe('Read more from blog');
});

it('registers anonymous and class based blade components under the module prefix', function (): void {
    expect(Blade::render('<x-blog::alert />'))->toContain('anonymous alert')
        ->and(Blade::render('<x-blog::badge />'))->toContain('class badge');
});

it('registers the module and its sub-namespaces with livewire', function (): void {
    $finder = app('livewire.finder');

    expect($finder->getClassNamespace('blog'))->toMatchArray([
        'classNamespace' => 'Fixture\\Blog\\Livewire',
        'classPath' => fixturesPath('blog/src/Livewire'),
    ])->and($finder->getClassNamespace('blog.pages'))->toMatchArray([
        'classNamespace' => 'Fixture\\Blog\\Livewire\\Pages',
    ]);
});

it('renders single file livewire pages from the pages sub-namespace', function (): void {
    Livewire::test('blog.pages::about')->assertSee('About blog');
});

it('registers sub-namespace directories as anonymous component namespaces', function (): void {
    expect(Blade::render('<x-blog.layouts::app>content</x-blog.layouts::app>'))
        ->toContain('<main class="blog-layout">content</main>');
});

it('registers module artisan commands', function (): void {
    $this->artisan('blog:ping')->expectsOutput('pong')->assertSuccessful();
});

it('discovers module event listeners', function (): void {
    $event = new PostPublished;

    event($event);

    expect($event->handled)->toBeTrue();
});

it('adds module migration directories to the migrator', function (): void {
    expect(app('migrator')->paths())->toContain(fixturesPath('blog/database/migrations'));
});

it('resolves factories and models inside modules', function (): void {
    expect(Factory::resolveFactoryName(Post::class))->toBe(PostFactory::class)
        ->and(PostFactory::new()->modelName())->toBe(Post::class)
        ->and(Factory::resolveFactoryName('App\\Models\\Invoice'))->toBe('Database\\Factories\\InvoiceFactory');
});

it('seeds a module with module:seed', function (): void {
    $this->artisan('module:seed', ['--module' => 'blog'])->assertSuccessful();

    expect(app('fixture.blog.seeded'))->toBeTrue();
});

it('lists the modules', function (): void {
    $this->artisan('module:list')
        ->expectsTable(['Module', 'Namespace', 'Path'], [['blog', 'Fixture\\Blog\\', fixturesPath('blog')]])
        ->assertSuccessful();
});

it('caches and clears the manifest and hooks into optimize', function (): void {
    $this->artisan('module:cache')->assertSuccessful();

    expect(File::exists($this->cachePath))->toBeTrue()
        ->and((require $this->cachePath)['modules'])->toHaveKey('blog')
        ->and(ServiceProvider::$optimizeCommands['modules'] ?? null)->toBe('module:cache')
        ->and(ServiceProvider::$optimizeClearCommands['modules'] ?? null)->toBe('module:clear');

    $this->artisan('module:clear')->assertSuccessful();

    expect(File::exists($this->cachePath))->toBeFalse();
});

it('reads the manifest from the cache file when it exists', function (): void {
    File::put($this->cachePath, '<?php return '.var_export([...app(ModuleManifest::class)->build(), 'modules' => []], true).';');
    app()->forgetInstance(ModuleManifest::class);

    expect(app(ModuleManifest::class)->modules())->toBe([]);
});

it('skips discovery types that are disabled in config', function (): void {
    config(['interlock.discover.routes' => false, 'interlock.discover.providers' => false]);
    app()->forgetInstance(ModuleManifest::class);

    $manifest = app(ModuleManifest::class)->build();

    expect($manifest['routes'])->toBe([])
        ->and($manifest['providers'])->toBe([])
        ->and($manifest['views'])->toHaveKey('blog');
});

it('finds no modules when the modules path does not exist', function (): void {
    config(['interlock.path' => fixturesPath('missing')]);
    app()->forgetInstance(ModuleManifest::class);

    expect(app(ModuleManifest::class)->build()['modules'])->toBe([]);
});
