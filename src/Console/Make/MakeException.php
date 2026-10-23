<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ExceptionMakeCommand;

class MakeException extends ExceptionMakeCommand
{
    use GeneratesInModule;
}
