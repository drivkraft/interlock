<?php

declare(strict_types=1);

namespace Drivkraft\Interlock;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lookup for the modules in the configured modules path.
 */
class ModuleRegistry
{
    /** @var Collection<string, Module>|null */
    protected ?Collection $modules = null;

    public function __construct(
        protected ModuleManifest $manifest,
    ) {}

    /**
     * @return Collection<string, Module>
     */
    public function modules(): Collection
    {
        return $this->modules ??= collect($this->manifest->modules())
            ->map(fn (array $attributes): Module => Module::fromArray($attributes));
    }

    public function module(?string $name): ?Module
    {
        if ($name === null) {
            return null;
        }

        return $this->modules()->get($name)
            ?? $this->modules()->first(fn (Module $module): bool => Str::kebab($module->name) === Str::kebab($name));
    }

    public function moduleForClass(string $class): ?Module
    {
        return $this->modules()->first(fn (Module $module): bool => $module->owns($class));
    }

    public function moduleForPath(string $path): ?Module
    {
        // realpath() returns backslashes on Windows, so compare with normalized separators.
        $path = str_replace('\\', '/', realpath($path) ?: $path);

        return $this->modules()->first(fn (Module $module): bool => str_starts_with($path, str_replace('\\', '/', $module->path).'/'));
    }

    public function path(): string
    {
        return $this->manifest->modulesPath();
    }

    public function reload(): void
    {
        $this->manifest->forget();
        $this->modules = null;
    }
}
