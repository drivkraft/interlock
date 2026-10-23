<?php

use Illuminate\Support\Facades\Route;

Route::get('/blog', fn () => 'blog')->name('fixture.blog.web');
