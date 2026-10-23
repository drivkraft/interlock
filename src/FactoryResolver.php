<?php

declare(strict_types=1);

namespace Drivkraft\Interlock;

use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Maps Module\X\Models\Y <=> Module\X\Database\Factories\YFactory and falls back to Laravel's defaults.
 */
class FactoryResolver
{
    public function __construct(
        protected ModuleRegistry $registry,
        protected string $appNamespace,
    ) {}

    public function register(): void
    {
        Factory::guessFactoryNamesUsing($this->factoryNameResolver());
        Factory::guessModelNamesUsing($this->modelNameResolver());
    }

    /**
     * @return Closure(string): string
     */
    public function factoryNameResolver(): Closure
    {
        return function (string $modelName): string {
            $factoryNamespace = $this->factoryNamespace();

            if ($module = $this->registry->moduleForClass($modelName)) {
                $relative = Str::startsWith($modelName, $module->qualify('Models\\'))
                    ? Str::after($modelName, $module->qualify('Models\\'))
                    : Str::after($modelName, $module->namespace);

                return $module->qualify($factoryNamespace.$relative.'Factory');
            }

            $relative = Str::startsWith($modelName, $this->appNamespace.'Models\\')
                ? Str::after($modelName, $this->appNamespace.'Models\\')
                : Str::after($modelName, $this->appNamespace);

            return $factoryNamespace.$relative.'Factory';
        };
    }

    /**
     * @return Closure(Factory<Model>): string
     */
    public function modelNameResolver(): Closure
    {
        return function (Factory $factory): string {
            $factoryClass = $factory::class;

            if ($module = $this->registry->moduleForClass($factoryClass)) {
                $relative = Str::replaceLast('Factory', '', Str::after($factoryClass, $module->qualify($this->factoryNamespace())));

                return $module->qualify('Models\\'.$relative);
            }

            $namespacedBasename = Str::replaceLast('Factory', '', Str::replaceFirst($this->factoryNamespace(), '', $factoryClass));
            $basename = Str::replaceLast('Factory', '', class_basename($factory));

            return class_exists($this->appNamespace.'Models\\'.$namespacedBasename)
                ? $this->appNamespace.'Models\\'.$namespacedBasename
                : $this->appNamespace.$basename;
        };
    }

    /**
     * Factory::$namespace is protected, so it is read through reflection.
     */
    protected function factoryNamespace(): string
    {
        return (string) (new ReflectionClass(Factory::class))->getStaticPropertyValue('namespace');
    }
}
