<?php

use Illuminate\Support\Facades\Route;

Route::get('/raw-blog', fn () => 'raw')->name('fixture.blog.raw');
