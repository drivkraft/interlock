<?php

namespace Fixture\Blog\Listeners;

use Fixture\Blog\Events\PostPublished;

class MarkPostHandled
{
    public function handle(PostPublished $event): void
    {
        $event->handled = true;
    }
}
