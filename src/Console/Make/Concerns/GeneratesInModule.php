<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make\Concerns;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

/**
 * Points a GeneratorCommand at a module when --module is passed.
 *
 * The root namespace stays App\ so stubs that reference {{ rootNamespace }} keep working;
 * class names and file paths are rewritten to the module instead.
 *
 * @mixin GeneratorCommand
 */
trait GeneratesInModule
{
    use InteractsWithModule;

    /**
     * Route sub-commands to their module:* counterparts with the same module
     * (module:model -mf calls module:migration and module:factory).
     *
     * @param  Command|string  $command
     * @param  array<string, mixed>  $arguments
     */
    public function call($command, array $arguments = []): int
    {
        if (is_string($command) && str_starts_with($command, 'make:')) {
            $moduleCommand = 'module:'.Str::after($command, 'make:');

            if ($this->getApplication()?->has($moduleCommand)) {
                $command = $moduleCommand;
                $arguments['--module'] = $this->module()->name;
            }
        }

        return parent::call($command, $arguments);
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        if ($configured = $this->configuredNamespace()) {
            return $configured;
        }

        $namespace = parent::getDefaultNamespace($rootNamespace);
        $module = $this->module();

        if (! Str::startsWith($namespace, $module->namespace)) {
            $namespace = Str::replaceFirst(rtrim($rootNamespace, '\\'), rtrim($module->namespace, '\\'), $namespace);
        }

        return $namespace;
    }

    /**
     * @param  string  $name
     */
    protected function qualifyClass($name): string
    {
        $name = str_replace('/', '\\', ltrim($name, '\\/'));

        if ($this->module()->owns($name)) {
            return $name;
        }

        return parent::qualifyClass($name);
    }

    protected function qualifyModel(string $model): string
    {
        $module = $this->module();
        $model = str_replace('/', '\\', ltrim($model, '\\/'));

        if ($module->owns($model) || Str::startsWith($model, $this->rootNamespace()) || class_exists('\\'.$model)) {
            return $model;
        }

        return $module->qualify('Models\\'.$model);
    }

    /**
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        $module = $this->module();

        if (! $module->owns($name)) {
            return parent::getPath($name);
        }

        return $module->sourcePath(str_replace('\\', '/', Str::after($name, $module->namespace)).'.php');
    }

    /**
     * The namespace from interlock.directories for this generator (module:enum => 'enum'), if any.
     */
    protected function configuredNamespace(): ?string
    {
        $directory = config('interlock.directories.'.Str::after((string) $this->getName(), 'module:'));

        if (! is_string($directory)) {
            return null;
        }

        return rtrim($this->module()->qualify(str_replace('/', '\\', trim($directory, '/'))), '\\');
    }
}
