<?php

use App\Http\Controllers\UserController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/v1/register', [UserController::class, 'register'])->withoutMiddleware([PreventRequestForgery::class]);
