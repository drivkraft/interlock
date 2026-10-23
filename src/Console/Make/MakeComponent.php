<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Illuminate\Foundation\Console\ComponentMakeCommand;

class MakeComponent extends ComponentMakeCommand
{
    use GeneratesInModule;

    /**
     * Point the generated view() call at the module view namespace.
     *
     * @param  string  $name
     */
    protected function buildClass($name): string
    {
        $view = $this->getView();

        return str_replace("'{$view}'", "'{$this->module()->name}::{$view}'", parent::buildClass($name));
    }

    /**
     * @param  string  $path
     */
    protected function viewPath($path = ''): string
    {
        return $this->module()->path('resources/views/'.ltrim($path, '/'));
    }
}
