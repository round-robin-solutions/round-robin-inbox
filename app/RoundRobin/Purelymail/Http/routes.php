<?php

Route::group([
    'middleware' => ['web', 'auth', 'roles'],
    'roles'      => ['admin'],
    'prefix'     => \Helper::getSubdirectory(),
    'namespace'  => 'App\RoundRobin\Purelymail\Http\Controllers',
], function () {
    Route::get('/purelymail/inboxes', 'PurelymailController@index')->name('purelymail.index');
    Route::post('/purelymail/inboxes', 'PurelymailController@create')->name('purelymail.create');
    Route::post('/purelymail/inboxes/setup', 'PurelymailController@setup')->name('purelymail.setup');
    Route::post('/purelymail/inboxes/connect', 'PurelymailController@connect')->name('purelymail.connect');
});

// Profile » Login & password: everyone for themselves, admins for anyone (checked in the controller).
Route::group([
    'middleware' => ['web', 'auth'],
    'prefix'     => \Helper::getSubdirectory(),
    'namespace'  => 'App\RoundRobin\Purelymail\Http\Controllers',
], function () {
    Route::get('/users/{id}/login', 'LoginController@show')->name('purelymail.login');
    Route::post('/users/{id}/login/recovery', 'LoginController@recovery')->name('purelymail.login.recovery');
    Route::post('/users/{id}/login/reset', 'LoginController@reset')->name('purelymail.login.reset');
});
