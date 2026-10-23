<?php

namespace Fixture\Blog\Console\Commands;

use Illuminate\Console\Command;

class PingCommand extends Command
{
    protected $signature = 'blog:ping';

    public function handle(): int
    {
        $this->line('pong');

        return self::SUCCESS;
    }
}
