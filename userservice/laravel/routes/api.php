<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminUserController;

Route::group([
    'middleware' => ['api'],
    'namespace' => '',
], function ($router) {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'reset']);

    Route::group([
        'middleware' => ['auth:sanctum', 'check.banned'],
        'namespace' => '',
        'prefix' => ''
    ], function ($router) {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/user/update-info', [UserController::class, 'changeInfo']);
        Route::get('/user/info', [UserController::class, 'getInfo']);
        Route::post('/user/avatar/change', [UserController::class, 'changeAvatar']);
        Route::get('/user', [AuthController::class, 'getUserFromToken']);
    });

    Route::group([
        'middleware' => ['auth:sanctum', 'role:admin'],
        'namespace' => '',
        'prefix' => 'admin'
    ], function ($router) {
        // User
        Route::post('create', [AdminUserController::class, 'create']);
        Route::get('users', [AdminUserController::class, 'getAllUser']);
        Route::get('ban/user/{id}', [AdminUserController::class, 'ban']);
        Route::get('unban/user/{id}', [AdminUserController::class, 'unban']);
        Route::delete('user/{id}', [AdminUserController::class, 'deleteUser']);
        Route::get('user/{id}', [AdminUserController::class, 'getUserDetails']);

        // Role
        Route::get('roles', [AdminUserController::class, 'getRole']);
        Route::post('roles', [AdminUserController::class, 'createRole']);
        Route::delete('roles/{id}', [AdminUserController::class, 'deleteRole']);
    });
});
