<?php

namespace Fixture\Blog\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()->instance('fixture.blog.seeded', true);
    }
}
