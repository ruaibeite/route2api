<?php

use think\facade\Route;

Route::post('user/login', 'User/login')->middleware('auth');
Route::get('user/:id', 'User/read');

Route::group('api', function () {
    Route::get('profile', 'Profile/read');
});

Route::resource('articles', 'Article');
