<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\NotificationMakeCommand;

class MakeNotification extends NotificationMakeCommand
{
    use GeneratesInModule;
}
