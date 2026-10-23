<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ChannelMakeCommand;

class MakeChannel extends ChannelMakeCommand
{
    use GeneratesInModule;
}
