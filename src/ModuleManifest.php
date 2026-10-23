<?php

declare(strict_types=1);

namespace Drivkraft\Interlock;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Events\DiscoverEvents;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use ReflectionClass;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * Discovers everything the package registers per module and caches it in one PHP file.
 *
 * @phpstan-type RouteFile array{path: string, group: string}
 * @phpstan-type LivewireNamespace array{paths: array{viewPath: string, classNamespace: string, classPath: string, classViewPath: string}, views: bool}
 * @phpstan-type Manifest array{
 *     modules: array<string, array{name: string, path: string, namespace: string, source: string}>,
 *     providers: list<class-string<ServiceProvider>>,
 *     routes: list<RouteFile>,
 *     views: array<string, string>,
 *     translations: array<string, string>,
 *     migrations: list<string>,
 *     components: array<array-key, string>,
 *     livewire: array<array-key, LivewireNamespace>,
 *     commands: list<class-string<Command>>,
 *     events: array<string, list<string>>,
 * }
 */
class ModuleManifest
{
    /** @var Manifest|null */
    protected ?array $manifest = null;

    /**
     * @param  array{path: string, source: string, namespace: string, discover: array<string, bool>, cache: string, livewire?: array{components?: string, namespaces?: array<string, string>}}  $config
     */
    public function __construct(
        protected Filesystem $files,
        protected array $config,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    public function get(string $key): array
    {
        return $this->manifest()[$key] ?? [];
    }

    /**
     * @return array<string, array{name: string, path: string, namespace: string, source: string}>
     */
    public function modules(): array
    {
        return $this->manifest()['modules'];
    }

    /**
     * @return Manifest
     */
    public function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if ($this->files->isFile($this->cachePath())) {
            return $this->manifest = $this->files->getRequire($this->cachePath());
        }

        return $this->manifest = $this->build();
    }

    /**
     * @return Manifest
     */
    public function build(): array
    {
        $modules = $this->discoverModules();

        return [
            'modules' => array_map(fn (Module $module): array => $module->toArray(), $modules),
            'providers' => array_values($this->discover('providers', $modules, $this->providers(...))),
            'routes' => array_values($this->discover('routes', $modules, $this->routes(...))),
            'views' => $this->discoverDirectory('views', $modules, 'resources/views'),
            'translations' => $this->discoverDirectory('translations', $modules, 'lang'),
            'migrations' => array_values($this->discoverDirectory('migrations', $modules, 'database/migrations')),
            'components' => $this->discover('components', $modules, $this->components(...)),
            'livewire' => $this->discover('livewire', $modules, $this->livewire(...)),
            'commands' => array_values($this->discover('commands', $modules, $this->commands(...))),
            'events' => $this->discover('events', $modules, $this->events(...)),
        ];
    }

    public function write(): string
    {
        $this->forget();

        $this->files->ensureDirectoryExists(dirname($this->cachePath()));
        $this->files->replace(
            $this->cachePath(),
            '<?php return '.var_export($this->build(), true).';'.PHP_EOL,
        );

        return $this->cachePath();
    }

    public function clear(): void
    {
        $this->files->delete($this->cachePath());
        $this->forget();
    }

    public function forget(): void
    {
        $this->manifest = null;
    }

    public function cachePath(): string
    {
        return $this->config['cache'];
    }

    public function modulesPath(): string
    {
        return rtrim($this->config['path'], '/');
    }

    /**
     * @return array<string, Module>
     */
    protected function discoverModules(): array
    {
        if (! $this->files->isDirectory($this->modulesPath())) {
            return [];
        }

        $modules = [];

        $finder = Finder::create()->files()->in($this->modulesPath())->depth(1)->name('composer.json')->sortByName();

        foreach ($finder as $composerFile) {
            // getPath() joins with DIRECTORY_SEPARATOR, so build the path with "/" to stay consistent on Windows.
            $name = $composerFile->getRelativePath();
            $path = $this->modulesPath().'/'.$name;

            $modules[$name] = new Module(
                name: $name,
                path: $path,
                namespace: $this->namespaceFromComposer($composerFile) ?? $this->config['namespace'].'\\'.Str::studly($name).'\\',
                source: $this->config['source'],
            );
        }

        return $modules;
    }

    /**
     * The PSR-4 namespace in the module's composer.json that points at the source directory.
     */
    protected function namespaceFromComposer(SplFileInfo $composerFile): ?string
    {
        $composer = json_decode((string) file_get_contents($composerFile->getPathname()), true);
        $source = trim($this->config['source'], '/');

        foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $directory) {
            foreach ((array) $directory as $candidate) {
                if (trim($candidate, '/') === $source) {
                    return $namespace;
                }
            }
        }

        return null;
    }

    /**
     * @template TResult
     *
     * @param  array<string, Module>  $modules
     * @param  callable(Module): array<array-key, TResult>  $callback
     * @return array<array-key, TResult>
     */
    protected function discover(string $type, array $modules, callable $callback): array
    {
        if (! ($this->config['discover'][$type] ?? true)) {
            return [];
        }

        $results = [];

        foreach ($modules as $module) {
            $results = array_merge_recursive($results, $callback($module));
        }

        return $results;
    }

    /**
     * @param  array<string, Module>  $modules
     * @return array<string, string>
     */
    protected function discoverDirectory(string $type, array $modules, string $relative): array
    {
        return $this->discover($type, $modules, fn (Module $module): array => $this->files->isDirectory($module->path($relative))
            ? [$module->name => $module->path($relative)]
            : []);
    }

    /**
     * @return list<class-string<ServiceProvider>>
     */
    protected function providers(Module $module): array
    {
        return $this->classesIn($module, 'Providers', ServiceProvider::class, recursive: false, pattern: '*ServiceProvider.php');
    }

    /**
     * @return list<array{path: string, group: string}>
     */
    protected function routes(Module $module): array
    {
        if (! $this->files->isDirectory($module->path('routes'))) {
            return [];
        }

        $routes = [];

        foreach (Finder::create()->files()->in($module->path('routes'))->depth(0)->name('*.php')->sortByName() as $file) {
            $routes[] = ['path' => $file->getRealPath(), 'group' => $file->getBasename('.php')];
        }

        return $routes;
    }

    /**
     * @return array<string, string>
     */
    protected function components(Module $module): array
    {
        return $this->files->isDirectory($module->sourcePath('View/Components'))
            ? [$module->name => $module->qualify('View\\Components')]
            : [];
    }

    /**
     * The {module}:: namespace plus one {module}.{name}:: namespace per interlock.livewire.namespaces entry.
     * Registered for every module so module:livewire works before the directories exist. Sub-namespaces
     * are also registered as view and anonymous component namespaces (views: true), like Livewire does
     * for its own component_namespaces.
     *
     * @return array<string, LivewireNamespace>
     */
    protected function livewire(Module $module): array
    {
        $config = $this->config['livewire'] ?? [];

        $namespaces = [$module->name => [
            'paths' => [
                'viewPath' => $module->path($config['components'] ?? 'resources/views/components'),
                'classNamespace' => $module->qualify('Livewire'),
                'classPath' => $module->sourcePath('Livewire'),
                'classViewPath' => $module->path('resources/views/livewire'),
            ],
            'views' => false,
        ]];

        foreach ($config['namespaces'] ?? [] as $name => $path) {
            $studly = Str::studly($name);

            $namespaces["{$module->name}.{$name}"] = [
                'paths' => [
                    'viewPath' => $module->path($path),
                    'classNamespace' => $module->qualify("Livewire\\{$studly}"),
                    'classPath' => $module->sourcePath("Livewire/{$studly}"),
                    'classViewPath' => $module->path('resources/views/livewire/'.Str::kebab($name)),
                ],
                'views' => true,
            ];
        }

        return $namespaces;
    }

    /**
     * @return list<class-string<Command>>
     */
    protected function commands(Module $module): array
    {
        return $this->classesIn($module, 'Console/Commands', Command::class);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function events(Module $module): array
    {
        if (! $this->files->isDirectory($module->sourcePath('Listeners'))) {
            return [];
        }

        $previous = DiscoverEvents::$guessClassNamesUsingCallback;

        DiscoverEvents::guessClassNamesUsing(fn (SplFileInfo $file, string $basePath): string => $module->classFromFile($file->getRealPath() ?: $file->getPathname()));

        try {
            return DiscoverEvents::within($module->sourcePath('Listeners'), $module->sourcePath());
        } finally {
            DiscoverEvents::$guessClassNamesUsingCallback = $previous;
        }
    }

    /**
     * Instantiable, autoloadable subclasses of $parent inside a source sub-directory.
     *
     * @template TParent of object
     *
     * @param  class-string<TParent>  $parent
     * @return list<class-string<TParent>>
     */
    protected function classesIn(Module $module, string $directory, string $parent, bool $recursive = true, string $pattern = '*.php'): array
    {
        if (! $this->files->isDirectory($module->sourcePath($directory))) {
            return [];
        }

        $finder = Finder::create()->files()->in($module->sourcePath($directory))->name($pattern)->sortByName();

        if (! $recursive) {
            $finder->depth(0);
        }

        $classes = [];

        foreach ($finder as $file) {
            $class = $module->classFromFile($file->getRealPath());

            if (class_exists($class) && is_subclass_of($class, $parent) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
