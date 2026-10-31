<?php

declare(strict_types=1);

namespace Drivkraft\Interlock\Console\Make;

use Drivkraft\Interlock\Console\Make\Concerns\GeneratesInModule;
use Livewire\Features\SupportConsoleCommands\Commands\FormCommand;

/**
 * module:livewire-form PostForm => {source}/Livewire/Forms/PostForm.php extending Livewire\Form.
 * Also reached through module:livewire --form.
 */
class MakeLivewireForm extends FormCommand
{
    use GeneratesInModule {
        configure as configureModule;
        getDefaultNamespace as public;
    }

    protected function configure(): void
    {
        $this->configureModule();

        $this->setName('module:livewire-form');
    }
}
