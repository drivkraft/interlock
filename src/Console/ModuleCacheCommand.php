<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console;

use Drivkraft\Interlock\ModuleManifest;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'module:cache')]
class ModuleCacheCommand extends Command
{
    protected $signature = 'module:cache';

    protected $description = 'Cache module discovery for faster boots';

    public function handle(ModuleManifest $manifest): int
    {
        $path = $manifest->write();

        $this->components->info("Modules cached to [{$path}].");

        return self::SUCCESS;
    }
}
