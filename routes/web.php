<?php

use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/hello-world', fn () => 'Hello world');
Route::get('/hello-world12', fn () => 'Hello world...123');

Route::get('/test-live', fn () => 'It works instantly now!');

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/v1/register', [UserController::class, 'register'])->withoutMiddleware([PreventRequestForgery::class]);
