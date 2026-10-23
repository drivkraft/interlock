<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console;

use Drivkraft\Interlock\Module;
use Drivkraft\Interlock\ModuleRegistry;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'module:list')]
class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List the application modules';

    public function handle(ModuleRegistry $registry): int
    {
        $this->table(
            ['Module', 'Namespace', 'Path'],
            $registry->modules()->map(fn (Module $module): array => [
                $module->name,
                $module->namespace,
                str_replace($this->laravel->basePath().'/', '', $module->path),
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
