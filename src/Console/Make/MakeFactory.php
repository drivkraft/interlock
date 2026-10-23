<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Database\Console\Factories\FactoryMakeCommand;
use Illuminate\Support\Str;

class MakeFactory extends FactoryMakeCommand
{
    use GeneratesInModule;

    /**
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->module()->qualify('Database\\Factories');
    }

    /**
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        $module = $this->module();
        $relative = Str::of($name)->after($module->qualify('Database\\Factories\\'))->finish('Factory')->replace('\\', '/');

        return $module->path("database/factories/{$relative}.php");
    }

    /**
     * The module model named after the factory (OrderFactory => Module\Shop\Models\Order),
     * even when that model has not been created yet.
     *
     * @param  string  $name
     */
    protected function guessModelName($name): string
    {
        return $this->qualifyModel(Str::of($name)->after($this->module()->qualify('Database\\Factories\\'))->beforeLast('Factory')->value());
    }
}
