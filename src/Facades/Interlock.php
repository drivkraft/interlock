<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Facades;

use Drivkraft\Interlock\ModuleRegistry;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<string, \Drivkraft\Interlock\Module> modules()
 * @method static \Drivkraft\Interlock\Module|null module(?string $name)
 * @method static \Drivkraft\Interlock\Module|null moduleForClass(string $class)
 * @method static \Drivkraft\Interlock\Module|null moduleForPath(string $path)
 * @method static string path()
 * @method static void reload()
 *
 * @see ModuleRegistry
 */
class Interlock extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ModuleRegistry::class;
    }
}
