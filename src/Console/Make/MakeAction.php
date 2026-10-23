<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Console\GeneratorCommand;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Console\Input\InputOption;

/**
 * module:action creates an action class that uses Lorisleiva\Actions\Concerns\AsAction
 * when lorisleiva/laravel-actions is installed, and a plain class otherwise.
 */
class MakeAction extends GeneratorCommand
{
    use GeneratesInModule;

    protected $name = 'make:action';

    protected $description = 'Create a new action class';

    protected $type = 'Action';

    protected function getStub(): string
    {
        $stubs = dirname(__DIR__, 3).'/stubs';

        return $this->usesLaravelActions() ? "{$stubs}/action.stub" : "{$stubs}/action.plain.stub";
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->configuredNamespace() ?? $this->module()->qualify('Actions');
    }

    protected function usesLaravelActions(): bool
    {
        return trait_exists(AsAction::class);
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
