<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Database;

use Drivkraft\Interlock\Console\Make\Concerns\InteractsWithModule;
use Illuminate\Database\Console\Seeds\SeedCommand as BaseSeedCommand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * module:seed --module=identity runs Module\Identity\Database\Seeders\DatabaseSeeder (or --class inside that namespace).
 */
class SeedCommand extends BaseSeedCommand
{
    use InteractsWithModule;

    protected function getSeeder(): Seeder
    {
        $module = $this->module();
        $class = $this->input->getArgument('class') ?? $this->input->getOption('class');

        if (! Str::contains($class, '\\') || Str::startsWith($class, 'Database\\Seeders\\')) {
            $class = $module->qualify('Database\\Seeders\\'.Str::after($class, 'Database\\Seeders\\'));
        }

        return $this->laravel->make($class)
            ->setContainer($this->laravel)
            ->setCommand($this);
    }
}
