<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\JobMakeCommand;

class MakeJob extends JobMakeCommand
{
    use GeneratesInModule;
}
