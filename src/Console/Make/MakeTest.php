<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\TestMakeCommand;
use Illuminate\Support\Str;

class MakeTest extends TestMakeCommand
{
    use GeneratesInModule;

    protected function rootNamespace(): string
    {
        return $this->module()->qualify('Tests');
    }

    /**
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        $relative = str_replace('\\', '/', Str::after($name, $this->rootNamespace().'\\'));

        return $this->module()->path("tests/{$relative}.php");
    }
}
