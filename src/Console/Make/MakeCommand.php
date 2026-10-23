<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ConsoleMakeCommand;
use Illuminate\Support\Str;

class MakeCommand extends ConsoleMakeCommand
{
    use GeneratesInModule;

    /**
     * Prefix the generated signature with the module name (identity:sync-users) instead of app:.
     *
     * @param  string  $stub
     * @param  string  $name
     */
    protected function replaceClass($stub, $name): string
    {
        $stub = parent::replaceClass($stub, $name);

        if ($this->option('command')) {
            return $stub;
        }

        $command = Str::of($name)->classBasename()->kebab()->value();

        return str_replace("app:{$command}", "{$this->module()->name}:{$command}", $stub);
    }
}
