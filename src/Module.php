<?php

declare(strict_types=1);

namespace Drivkraft\Interlock;

use Illuminate\Support\Str;

/**
 * A single module on disk, e.g. modules/identity => \Module\Identity\.
 */
final readonly class Module
{
    /**
     * @param  string  $name  Directory name, also the view/translation/component prefix.
     * @param  string  $path  Absolute path to the module root.
     * @param  string  $namespace  Root PHP namespace with trailing backslash.
     * @param  string  $source  Directory inside the module that maps to $namespace.
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
        public string $source = 'src',
    ) {}

    /**
     * @param  array{name: string, path: string, namespace: string, source: string}  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(...$attributes);
    }

    /**
     * @return array{name: string, path: string, namespace: string, source: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'path' => $this->path,
            'namespace' => $this->namespace,
            'source' => $this->source,
        ];
    }

    public function path(string $relative = ''): string
    {
        return rtrim($this->path.'/'.ltrim($relative, '/'), '/');
    }

    public function sourcePath(string $relative = ''): string
    {
        return $this->path(trim($this->source.'/'.ltrim($relative, '/'), '/'));
    }

    /**
     * Prefix a class name with the module namespace: qualify('Models\User') => Module\Identity\Models\User.
     */
    public function qualify(string $class = ''): string
    {
        return $this->namespace.ltrim($class, '\\');
    }

    public function studlyName(): string
    {
        return Str::studly($this->name);
    }

    public function owns(string $class): bool
    {
        return str_starts_with(ltrim($class, '\\'), $this->namespace);
    }

    /**
     * Fully qualified class name for a PHP file inside the source directory.
     */
    public function classFromFile(string $file): string
    {
        $relative = Str::after(realpath($file) ?: $file, realpath($this->sourcePath()) ?: $this->sourcePath());

        return $this->qualify(str_replace('/', '\\', trim(Str::beforeLast($relative, '.php'), '/')));
    }
}
