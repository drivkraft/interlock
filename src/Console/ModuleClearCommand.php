<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console;

use Drivkraft\Interlock\ModuleManifest;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'module:clear')]
class ModuleClearCommand extends Command
{
    protected $signature = 'module:clear';

    protected $description = 'Remove the module discovery cache';

    public function handle(ModuleManifest $manifest): int
    {
        $manifest->clear();

        $this->components->info('Module cache cleared.');

        return self::SUCCESS;
    }
}
