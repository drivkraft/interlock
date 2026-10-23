<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Tests;

use Drivkraft\Interlock\InterlockServiceProvider;
use Illuminate\Encryption\Encrypter;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LivewireServiceProvider::class,
            InterlockServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC')));
    }
}
