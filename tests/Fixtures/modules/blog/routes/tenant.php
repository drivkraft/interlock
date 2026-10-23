<?php

use Illuminate\Support\Facades\Route;

Route::get('/tenant-blog', fn () => 'tenant')->name('fixture.blog.tenant');
