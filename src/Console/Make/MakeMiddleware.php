<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Routing\Console\MiddlewareMakeCommand;

class MakeMiddleware extends MiddlewareMakeCommand
{
    use GeneratesInModule;
}
