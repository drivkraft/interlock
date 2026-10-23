<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ScopeMakeCommand;

class MakeScope extends ScopeMakeCommand
{
    use GeneratesInModule;
}
