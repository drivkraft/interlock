<?php

declare(strict_types=1);

use Drivkraft\Interlock\Module;

beforeEach(function (): void {
    $this->module = new Module('blog', fixturesPath('blog'), 'Fixture\\Blog\\');
});

it('round trips through an array', function (): void {
    expect(Module::fromArray($this->module->toArray()))->toEqual($this->module)
        ->and($this->module->toArray())->toBe([
            'name' => 'blog',
            'path' => fixturesPath('blog'),
            'namespace' => 'Fixture\\Blog\\',
            'source' => 'src',
        ]);
});

it('builds paths inside the module and its source directory', function (): void {
    expect($this->module->path())->toBe(fixturesPath('blog'))
        ->and($this->module->path('/routes/web.php'))->toBe(fixturesPath('blog/routes/web.php'))
        ->and($this->module->sourcePath())->toBe(fixturesPath('blog/src'))
        ->and($this->module->sourcePath('Models'))->toBe(fixturesPath('blog/src/Models'));
});

it('qualifies class names with the module namespace', function (): void {
    expect($this->module->qualify('Models\\Post'))->toBe('Fixture\\Blog\\Models\\Post')
        ->and($this->module->qualify('\\Models\\Post'))->toBe('Fixture\\Blog\\Models\\Post')
        ->and($this->module->studlyName())->toBe('Blog');
});

it('knows which classes it owns', function (): void {
    expect($this->module->owns('Fixture\\Blog\\Models\\Post'))->toBeTrue()
        ->and($this->module->owns('\\Fixture\\Blog\\Models\\Post'))->toBeTrue()
        ->and($this->module->owns('Fixture\\Blogger\\Models\\Post'))->toBeFalse();
});

it('resolves the class name of a source file', function (): void {
    expect($this->module->classFromFile(fixturesPath('blog/src/Models/Post.php')))->toBe('Fixture\\Blog\\Models\\Post');
});
