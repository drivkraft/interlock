<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Console\GeneratorCommand;
use Illuminate\Foundation\Console\ProviderMakeCommand;
use Illuminate\Support\Str;

class MakeProvider extends ProviderMakeCommand
{
    use GeneratesInModule;

    /**
     * Module providers are discovered, so unlike make:provider the class is not
     * added to bootstrap/providers.php.
     */
    public function handle(): ?bool
    {
        $result = GeneratorCommand::handle();

        if ($result !== false && ! $this->isDiscoverable($this->qualifyClass($this->getNameInput()))) {
            $this->components->warn('This provider is not discovered automatically. Only top-level Providers/*ServiceProvider classes are, so register it yourself.');
        }

        return $result;
    }

    protected function isDiscoverable(string $class): bool
    {
        return config('interlock.discover.providers', true)
            && Str::endsWith($class, 'ServiceProvider')
            && $class === $this->module()->qualify('Providers\\'.class_basename($class));
    }
}
