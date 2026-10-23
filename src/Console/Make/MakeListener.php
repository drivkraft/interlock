<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ListenerMakeCommand;
use Illuminate\Support\Str;

class MakeListener extends ListenerMakeCommand
{
    use GeneratesInModule;

    /**
     * Resolve a short --event name to the module's Events namespace instead of App\Events.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $event = $this->option('event');

        if (is_string($event) && ! Str::startsWith($event, [$this->laravel->getNamespace(), 'Illuminate', '\\'])) {
            $module = $this->module();
            $event = str_replace('/', '\\', $event);

            $this->input->setOption('event', '\\'.($module->owns($event) ? $event : $module->qualify('Events\\'.$event)));
        }

        return parent::buildClass($name);
    }
}
