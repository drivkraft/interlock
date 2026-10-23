<?php

declare(strict_types=1);

use Drivkraft\Interlock\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Absolute path to the fixture modules directory.
 */
function fixturesPath(string $path = ''): string
{
    return rtrim(__DIR__.'/Fixtures/modules/'.$path, '/');
}
