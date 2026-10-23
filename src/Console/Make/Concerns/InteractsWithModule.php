<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make\Concerns;

use Drivkraft\Interlock\Module;
use Drivkraft\Interlock\ModuleRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

/**
 * Turns a Laravel command into its module:* counterpart (make:model => module:model,
 * db:seed => module:seed) with a --module option that is prompted for when missing.
 *
 * @mixin Command
 */
trait InteractsWithModule
{
    protected function module(): Module
    {
        $name = $this->option('module');

        if (! is_string($name) || $name === '') {
            throw new InvalidOptionException('The --module option is required.');
        }

        return $this->getLaravel()->make(ModuleRegistry::class)->module($name)
            ?? throw new InvalidOptionException(sprintf('The "%s" module does not exist.', $name));
    }

    protected function configure(): void
    {
        parent::configure();

        $this->setName('module:'.Str::after((string) $this->getName(), ':'));

        $this->getDefinition()->addOption(
            new InputOption('module', null, InputOption::VALUE_REQUIRED, 'The module to run inside'),
        );
    }

    /**
     * Ask for (and validate) the module before Laravel prompts for any missing arguments.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        if (! $input->getOption('module')) {
            $modules = $this->getLaravel()->make(ModuleRegistry::class)->modules()->keys()->all();

            if ($modules === []) {
                throw new InvalidOptionException('No modules found. Create one with php artisan module:make.');
            }

            $input->setOption('module', select(
                label: 'Which module?',
                options: array_combine($modules, $modules),
            ));
        }

        $this->module();

        parent::interact($input, $output);
    }
}
