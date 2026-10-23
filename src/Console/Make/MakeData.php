<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Console\GeneratorCommand;
use Spatie\LaravelData\Data;
use Symfony\Component\Console\Input\InputOption;

/**
 * module:data creates a data object that extends Spatie\LaravelData\Data when
 * spatie/laravel-data is installed, and a plain readonly class otherwise.
 */
class MakeData extends GeneratorCommand
{
    use GeneratesInModule;

    protected $name = 'make:data';

    protected $description = 'Create a new data object';

    protected $type = 'Data';

    protected function getStub(): string
    {
        $stubs = dirname(__DIR__, 3).'/stubs';

        return $this->usesLaravelData() ? "{$stubs}/data.stub" : "{$stubs}/data.plain.stub";
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->configuredNamespace() ?? $this->module()->qualify('Data');
    }

    protected function usesLaravelData(): bool
    {
        return class_exists(Data::class);
    }

    /**
     * @return list<array{string, string|null, int, string}>
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if it already exists'],
        ];
    }
}
