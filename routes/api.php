<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FreelancerProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BriefcaseController;
use App\Http\Controllers\AvailabilityController;


// Auth routes (sin protección)
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// catch-all preflight response (covers swagger UI and browsers)
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->where('any', '.*');

// Rutas protegidas por autenticación
Route::middleware('auth.api')->group(function () {
    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    // Activity Logs - Acceso para todos autenticados
    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/summary', [ActivityLogController::class, 'summary']);
    
    // Roles - Solo admin puede crear/editar/eliminar
    Route::get('/roles', [RoleController::class, 'index']);
    Route::middleware('role:admin')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    });

    // Users - Gestión de usuarios
    Route::middleware('role:admin,empresa')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    }); 
});
 // Freelancer Profiles - Gestión de perfiles de freelancers
        Route::get('/profiles', [FreelancerProfileController::class, 'index']);
        Route::post('/profiles', [FreelancerProfileController::class, 'store']);
        Route::get('/profiles/{id}', [FreelancerProfileController::class, 'show']);
        Route::put('/profiles/{id}', [FreelancerProfileController::class, 'update']);
        Route::delete('/profiles/{id}', [FreelancerProfileController::class, 'destroy']);

    // Services - Gestión de servicios
        Route::get('/services', [ServiceController::class, 'index']);
        Route::post('/services', [ServiceController::class, 'store']);
        Route::get('/services/{id}', [ServiceController::class, 'show']);
        Route::put('/services/{id}', [ServiceController::class, 'update']);
        Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    // Briefcases - Gestión de portafolios
        Route::get('/briefcases', [BriefcaseController::class, 'index']);
        Route::post('/briefcases', [BriefcaseController::class, 'store']);
        Route::get('/briefcases/{id}', [BriefcaseController::class, 'show']);
        Route::put('/briefcases/{id}', [BriefcaseController::class, 'update']);
        Route::delete('/briefcases/{id}', [BriefcaseController::class, 'destroy']);


    // Availabilities - Gestión de disponibilidades
        Route::get('/availabilities', [AvailabilityController::class, 'index']);
        Route::post('/availabilities', [AvailabilityController::class, 'store']);
        Route::get('/availabilities/{id}', [AvailabilityController::class, 'show']);
        Route::put('/availabilities/{id}', [AvailabilityController::class, 'update']);
        Route::delete('/availabilities/{id}', [AvailabilityController::class, 'destroy']);

