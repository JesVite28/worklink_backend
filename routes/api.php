<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\FreelancerProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BriefcaseController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ContractRequestController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\Api\ChatBotController;


/*
|--------------------------------------------------------------------------
| Public Auth Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/chatbot/message', [ChatBotController::class, 'sendPublicMessage'])
    ->middleware('throttle:20,1');

/*
|--------------------------------------------------------------------------
| CORS Preflight
|--------------------------------------------------------------------------
*/

Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
})->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth.api')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::post('/chatbot/auth-message', [ChatBotController::class, 'sendAuthMessage'])
        ->middleware('throttle:30,1');
    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/summary', [ActivityLogController::class, 'summary']);

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get('/roles', [RoleController::class, 'index']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::get('/roles/{id}', [RoleController::class, 'show']);
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Users
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin,empresa')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    /*
    |--------------------------------------------------------------------------
    | Freelancer Profiles
    |--------------------------------------------------------------------------
    */

    Route::get('/profiles', [FreelancerProfileController::class, 'index']);
    Route::post('/profiles', [FreelancerProfileController::class, 'store']);
    Route::get('/profiles/{id}', [FreelancerProfileController::class, 'show']);
    Route::put('/profiles/{id}', [FreelancerProfileController::class, 'update']);
    Route::delete('/profiles/{id}', [FreelancerProfileController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    Route::get('/services', [ServiceController::class, 'index']);
    Route::post('/services', [ServiceController::class, 'store']);
    Route::get('/services/{id}', [ServiceController::class, 'show']);
    Route::put('/services/{id}', [ServiceController::class, 'update']);
    Route::delete('/services/{id}', [ServiceController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Briefcases / Portfolios
    |--------------------------------------------------------------------------
    */

    Route::get('/briefcases', [BriefcaseController::class, 'index']);
    Route::post('/briefcases', [BriefcaseController::class, 'store']);
    Route::get('/briefcases/{id}', [BriefcaseController::class, 'show']);
    Route::put('/briefcases/{id}', [BriefcaseController::class, 'update']);
    Route::delete('/briefcases/{id}', [BriefcaseController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Availabilities
    |--------------------------------------------------------------------------
    */

    Route::get('/availabilities', [AvailabilityController::class, 'index']);
    Route::post('/availabilities', [AvailabilityController::class, 'store']);
    Route::get('/availabilities/{id}', [AvailabilityController::class, 'show']);
    Route::put('/availabilities/{id}', [AvailabilityController::class, 'update']);
    Route::delete('/availabilities/{id}', [AvailabilityController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Contract Requests
    |--------------------------------------------------------------------------
    */

    Route::get('/contract-requests', [ContractRequestController::class, 'index']);
    Route::post('/contract-requests', [ContractRequestController::class, 'store']);
    Route::get('/contract-requests/{id}', [ContractRequestController::class, 'show']);
    Route::put('/contract-requests/{id}', [ContractRequestController::class, 'update']);
    Route::delete('/contract-requests/{id}', [ContractRequestController::class, 'destroy']);

    /*
    |--------------------------------------------------------------------------
    | Contracts
    |--------------------------------------------------------------------------
    */

    Route::get('/contracts', [ContractController::class, 'index']);
    Route::post('/contracts', [ContractController::class, 'store']);
    Route::get('/contracts/{id}', [ContractController::class, 'show']);
    Route::put('/contracts/{id}', [ContractController::class, 'update']);
    Route::delete('/contracts/{id}', [ContractController::class, 'destroy']);
});