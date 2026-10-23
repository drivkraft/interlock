<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\InteractsWithModule;
use Illuminate\Support\Str;
use Livewire\Features\SupportConsoleCommands\Commands\MakeCommand;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * module:livewire orders --kind=pages => make:livewire shop.pages::orders, with every make:livewire option.
 * Works because each module (and each interlock.livewire.namespaces entry) is a Livewire namespace.
 */
class MakeLivewire extends MakeCommand
{
    use InteractsWithModule {
        configure as configureModule;
        interact as interactWithModule;
    }

    protected function configure(): void
    {
        $this->configureModule();

        $this->getDefinition()->addOption(
            new InputOption('kind', null, InputOption::VALUE_REQUIRED, 'component, or a interlock.livewire.namespaces key such as pages'),
        );
    }

    /**
     * Ask for the module, then the kind of component, before Livewire asks for the name.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->interactWithModule($input, $output);

        if ($input->getOption('kind') || str_contains((string) $input->getArgument('name'), '::')) {
            return;
        }

        $module = $this->module()->name;

        $kinds = ['component' => "Component ({$module}::)"];

        foreach ($this->namespaceKinds() as $name) {
            $kinds[$name] = Str::headline(Str::singular($name))." ({$module}.{$name}::)";
        }

        $input->setOption('kind', select(label: 'What kind of component?', options: $kinds, default: 'component'));
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! is_string($name) || $name === '') {
            $name = text('What should the component be named?', required: true);
        }

        if (! str_contains($name, '::')) {
            $name = $this->livewireNamespace().'::'.$name;
        }

        $this->input->setArgument('name', $name);

        return (int) parent::handle();
    }

    protected function livewireNamespace(): string
    {
        $module = $this->module()->name;
        $kind = $this->option('kind');

        if (! is_string($kind) || $kind === '' || $kind === 'component') {
            return $module;
        }

        if (! in_array($kind, $this->namespaceKinds(), true)) {
            throw new InvalidOptionException(sprintf('Unknown kind "%s". Use component or one of: %s.', $kind, implode(', ', $this->namespaceKinds())));
        }

        return "{$module}.{$kind}";
    }

    /**
     * The interlock.livewire.namespaces keys, such as pages and layouts.
     *
     * @return list<string>
     */
    protected function namespaceKinds(): array
    {
        return array_map(strval(...), array_keys((array) config('interlock.livewire.namespaces', [])));
    }
}
