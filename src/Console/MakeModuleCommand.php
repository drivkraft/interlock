<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console;

use Drivkraft\Interlock\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;

/**
 * Creates a module from stubs. The application composer.json is never edited: module
 * autoloading comes from wikimedia/composer-merge-plugin merging modules/{name}/composer.json.
 *
 * Placeholders available in stub destinations and contents: StubBasePath, StubModuleNamespace,
 * StubModuleNameSingular, StubModuleNamePlural, StubModuleName,
 * StubClassNamePrefix, StubSourceDirectory, StubMigrationPrefix,
 * StubFullyQualifiedTestCaseBase, StubTestCaseBase.
 *
 * The parts (--web, --api, --views, ...) only apply to the package stubs; custom stubs from
 * config('interlock.stubs') are always written in full.
 */
#[AsCommand(name: 'make:module')]
class MakeModuleCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'make:module
        {name : The name of the module}
        {--web : Create routes/web.php}
        {--api : Create routes/api.php}
        {--views : Create resources/views}
        {--lang : Create the lang directory}
        {--migrations : Create database/migrations}
        {--factories : Create database/factories}
        {--seeders : Create database/seeders}
        {--tests : Create the tests directory}
        {--all : Create every part}
        {--no-dump : Skip composer dump-autoload}';

    protected $description = 'Create a new application module';

    /**
     * Optional module parts: option => label.
     *
     * @var array<string, string>
     */
    protected array $parts = [
        'web' => 'Web routes',
        'api' => 'API routes',
        'views' => 'Views',
        'lang' => 'Translations',
        'migrations' => 'Migrations',
        'factories' => 'Factories',
        'seeders' => 'Seeders',
        'tests' => 'Tests',
    ];

    /**
     * Parts created when none are chosen and the command runs without interaction.
     *
     * @var list<string>
     */
    protected array $defaultParts = ['web', 'views', 'lang', 'migrations', 'factories', 'seeders', 'tests'];

    public function handle(Filesystem $files, ModuleRegistry $registry, Composer $composer): int
    {
        $moduleName = Str::kebab((string) $this->argument('name'));
        $basePath = $registry->path().'/'.$moduleName;
        $placeholders = $this->placeholders($moduleName, $basePath);

        if ($files->isDirectory($basePath)) {
            $this->components->warn("Module directory [{$basePath}] already exists, only missing files are written.");
        }

        foreach ($this->stubs($this->selectedParts()) as $destination => $stubFile) {
            if (! $files->isFile($stubFile)) {
                $this->components->error("Stub file not found: {$stubFile}");

                return self::FAILURE;
            }

            $destination = strtr($destination, $placeholders);
            $target = "{$basePath}/{$destination}";

            if ($files->exists($target)) {
                $this->components->twoColumnDetail($destination, '<fg=yellow;options=bold>SKIPPED</>');

                continue;
            }

            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, strtr($files->get($stubFile), $placeholders));

            $this->components->twoColumnDetail($destination, '<fg=green;options=bold>CREATED</>');
        }

        $this->call(ModuleClearCommand::class);

        if (! $this->option('no-dump')) {
            $composer->dumpAutoloads();
        }

        $registry->reload();

        $this->components->info("Module [{$moduleName}] created in [{$basePath}].");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{string, string}>
     */
    protected function promptForMissingArgumentsUsing(): array
    {
        return ['name' => ['What should the module be called?', 'E.g. billing']];
    }

    /**
     * After the name, ask which parts to create unless any part option was passed.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($this->receivedPartOptions($input) || ! $this->usesPackageStubs()) {
            return;
        }

        $selected = multiselect(
            label: 'Which parts should the module have?',
            options: $this->parts,
            default: $this->defaultParts,
            hint: 'composer.json and the service provider are always created.',
        );

        foreach (array_filter($selected, is_string(...)) as $part) {
            $input->setOption($part, true);
        }
    }

    protected function receivedPartOptions(InputInterface $input): bool
    {
        return collect([...array_keys($this->parts), 'all'])->contains(fn (string $part): bool => $input->getOption($part) === true);
    }

    /**
     * @return list<string>
     */
    protected function selectedParts(): array
    {
        if ($this->option('all')) {
            return array_keys($this->parts);
        }

        $selected = array_values(array_filter(array_keys($this->parts), fn (string $part): bool => $this->option($part) === true));

        if ($selected === [] && ! $this->input->isInteractive()) {
            return $this->defaultParts;
        }

        return $selected;
    }

    protected function usesPackageStubs(): bool
    {
        $custom = config('interlock.stubs');

        return ! is_array($custom) || $custom === [];
    }

    /**
     * Applied with strtr(), which matches the longest key first (StubModuleNamePlural before StubModuleName).
     *
     * @return array<string, string>
     */
    protected function placeholders(string $moduleName, string $basePath): array
    {
        $namespace = (string) config('interlock.namespace');
        $testsBase = (string) config('interlock.tests_base');

        return [
            'StubBasePath' => $basePath,
            'StubModuleNamespace' => $namespace,
            'StubModuleNameSingular' => Str::singular($moduleName),
            'StubModuleNamePlural' => Str::plural($moduleName),
            'StubModuleName' => $moduleName,
            'StubClassNamePrefix' => Str::studly($moduleName),
            'StubSourceDirectory' => trim((string) config('interlock.source'), '/'),
            'StubMigrationPrefix' => date('Y_m_d_His'),
            'StubFullyQualifiedTestCaseBase' => $testsBase,
            'StubTestCaseBase' => class_basename($testsBase),
        ];
    }

    /**
     * @param  list<string>  $parts
     * @return array<string, string> module-relative destination => absolute stub path
     */
    protected function stubs(array $parts): array
    {
        if (! $this->usesPackageStubs()) {
            return config('interlock.stubs');
        }

        $stubs = dirname(__DIR__, 2).'/stubs';

        $optional = [
            'web' => ['routes/web.php' => "{$stubs}/web-routes.php.stub"],
            'api' => ['routes/api.php' => "{$stubs}/api-routes.php.stub"],
            'views' => ['resources/views/.gitkeep' => "{$stubs}/.gitkeep"],
            'lang' => ['lang/.gitkeep' => "{$stubs}/.gitkeep"],
            'migrations' => ['database/migrations/.gitkeep' => "{$stubs}/.gitkeep"],
            'factories' => ['database/factories/.gitkeep' => "{$stubs}/.gitkeep"],
            'seeders' => ['database/seeders/DatabaseSeeder.php' => "{$stubs}/DatabaseSeeder.php.stub"],
            'tests' => ['tests/.gitkeep' => "{$stubs}/.gitkeep"],
        ];

        return array_merge(
            [
                'composer.json' => "{$stubs}/composer.json.stub",
                'StubSourceDirectory/Providers/StubClassNamePrefixServiceProvider.php' => "{$stubs}/ServiceProvider.php.stub",
            ],
            ...array_map(fn (string $part): array => $optional[$part], $parts),
        );
    }
}
