<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Database\Console\Seeds\SeederMakeCommand;
use Illuminate\Support\Str;

class MakeSeeder extends SeederMakeCommand
{
    use GeneratesInModule;

    protected function rootNamespace(): string
    {
        return $this->module()->qualify('Database\\Seeders\\');
    }

    /**
     * @param  string  $name
     */
    protected function getPath($name): string
    {
        $relative = str_replace('\\', '/', Str::after($name, $this->rootNamespace()));

        return $this->module()->path("database/seeders/{$relative}.php");
    }
}
