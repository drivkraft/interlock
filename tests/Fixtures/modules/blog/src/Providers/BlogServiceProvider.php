<?php

namespace Fixture\Blog\Providers;

use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->instance('fixture.blog.registered', true);
    }
}
