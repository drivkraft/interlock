<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\InteractsWithModule;
use Illuminate\Database\Console\Migrations\MigrateMakeCommand;

class MakeMigration extends MigrateMakeCommand
{
    use InteractsWithModule;

    protected function getMigrationPath(): string
    {
        if ($this->input->getOption('path')) {
            return parent::getMigrationPath();
        }

        $path = $this->module()->path('database/migrations');

        $this->laravel->make('files')->ensureDirectoryExists($path);

        return $path;
    }
}
