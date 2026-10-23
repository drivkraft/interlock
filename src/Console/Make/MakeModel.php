<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ModelMakeCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\multiselect;

class MakeModel extends ModelMakeCommand
{
    use GeneratesInModule {
        interact as interactWithModule;
    }

    /**
     * Ask for the module and name, then which related files to create. Unlike make:model this
     * also asks when the name was passed, as long as no option other than --module was given.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->interactWithModule($input, $output);

        if ($this->isReservedName($this->getNameInput()) || $this->receivedOptionsBesidesModule($input)) {
            return;
        }

        $related = multiselect('Would you like any of the following?', [
            'seed' => 'Database Seeder',
            'factory' => 'Factory',
            'requests' => 'Form Requests',
            'migration' => 'Migration',
            'policy' => 'Policy',
            'resource' => 'Resource Controller',
        ]);

        foreach (array_filter($related, is_string(...)) as $option) {
            $input->setOption($option, true);
        }
    }

    /**
     * Prompting is handled in interact(), so the parent's prompt after a missing name is skipped.
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void {}

    protected function receivedOptionsBesidesModule(InputInterface $input): bool
    {
        return collect($this->getDefinition()->getOptions())
            ->reject(fn (InputOption $option): bool => $option->getName() === 'module')
            ->contains(fn (InputOption $option): bool => $input->getOption($option->getName()) !== $option->getDefault());
    }

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->configuredNamespace() ?? $this->module()->qualify('Models');
    }

    /**
     * @return array<string, string>
     */
    protected function buildFactoryReplacements(): array
    {
        $replacements = parent::buildFactoryReplacements();

        if (isset($replacements['{{ factory }}'])) {
            $replacements['{{ factory }}'] = str_replace(
                '\\Database\\Factories\\',
                '\\'.$this->module()->qualify('Database\\Factories\\'),
                $replacements['{{ factory }}'],
            );
        }

        return $replacements;
    }
}
