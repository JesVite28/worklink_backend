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

Route::post(
    '/chatbot/message',
    [ChatBotController::class, 'sendPublicMessage']
)->middleware('throttle:20,1');


/*
|--------------------------------------------------------------------------
| Public Exploration Routes
|--------------------------------------------------------------------------
|
| Estas rutas pueden consultarse sin iniciar sesión.
| Se utiliza el prefijo /public para mantenerlas separadas
| de las rutas privadas utilizadas por el dashboard.
|
*/

Route::prefix('public')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Freelancer Profiles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profiles',
        [FreelancerProfileController::class, 'publicIndex']
    );

    Route::get(
        '/profiles/user/{userId}',
        [FreelancerProfileController::class, 'publicShowByUserId']
    )->whereNumber('userId');

    Route::get(
        '/profiles/{id}',
        [FreelancerProfileController::class, 'publicShow']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Public Services
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/services',
        [ServiceController::class, 'publicIndex']
    );

    Route::get(
        '/services/{id}',
        [ServiceController::class, 'publicShow']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Public Briefcases / Portfolios
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/briefcases',
        [BriefcaseController::class, 'publicIndex']
    );

    Route::get(
        '/briefcases/freelancer/{freelancerId}',
        [BriefcaseController::class, 'publicByFreelancer']
    )->whereNumber('freelancerId');

    Route::get(
        '/briefcases/{id}',
        [BriefcaseController::class, 'publicShow']
    )->whereNumber('id');
});


/*
|--------------------------------------------------------------------------
| CORS Preflight
|--------------------------------------------------------------------------
*/

Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        )
        ->header(
            'Access-Control-Allow-Headers',
            'Content-Type, Authorization, X-Requested-With'
        );
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

    Route::post(
        '/chatbot/auth-message',
        [ChatBotController::class, 'sendAuthMessage']
    )->middleware('throttle:30,1');


    /*
    |--------------------------------------------------------------------------
    | My Account
    |--------------------------------------------------------------------------
    |
    | Cualquier usuario autenticado puede actualizar o eliminar
    | únicamente su propia cuenta.
    |
    */

    Route::put(
        '/users/me',
        [UserController::class, 'updateMe']
    );

    Route::patch(
        '/users/me',
        [UserController::class, 'updateMe']
    );

    Route::delete(
        '/users/me',
        [UserController::class, 'destroyMe']
    );


    /*
    |--------------------------------------------------------------------------
    | Activity Logs
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/activity-logs',
        [ActivityLogController::class, 'index']
    );

    Route::get(
        '/activity-logs/summary',
        [ActivityLogController::class, 'summary']
    );


    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/roles',
        [RoleController::class, 'index']
    );

    Route::middleware('role:admin')->group(function () {

        Route::post(
            '/roles',
            [RoleController::class, 'store']
        );

        Route::get(
            '/roles/{id}',
            [RoleController::class, 'show']
        )->whereNumber('id');

        Route::put(
            '/roles/{id}',
            [RoleController::class, 'update']
        )->whereNumber('id');

        Route::patch(
            '/roles/{id}',
            [RoleController::class, 'update']
        )->whereNumber('id');

        Route::delete(
            '/roles/{id}',
            [RoleController::class, 'destroy']
        )->whereNumber('id');
    });


    /*
    |--------------------------------------------------------------------------
    | User Administration
    |--------------------------------------------------------------------------
    |
    | Solamente los administradores pueden consultar, crear,
    | modificar o eliminar cuentas ajenas.
    |
    */

    Route::middleware('role:admin')->group(function () {

        Route::get(
            '/users',
            [UserController::class, 'index']
        );

        Route::post(
            '/users',
            [UserController::class, 'store']
        );

        Route::get(
            '/users/{id}',
            [UserController::class, 'show']
        )->whereNumber('id');

        Route::put(
            '/users/{id}',
            [UserController::class, 'update']
        )->whereNumber('id');

        Route::patch(
            '/users/{id}',
            [UserController::class, 'update']
        )->whereNumber('id');

        Route::delete(
            '/users/{id}',
            [UserController::class, 'destroy']
        )->whereNumber('id');
    });


    /*
    |--------------------------------------------------------------------------
    | Freelancer Profiles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profiles',
        [FreelancerProfileController::class, 'index']
    );

    Route::post(
        '/profiles',
        [FreelancerProfileController::class, 'store']
    );

    /*
     * Esta ruta debe declararse antes de /profiles/{id}.
     */
    Route::get(
        '/profiles/user/{userId}',
        [FreelancerProfileController::class, 'showByUserId']
    )->whereNumber('userId');

    Route::get(
        '/profiles/{id}',
        [FreelancerProfileController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/profiles/{id}',
        [FreelancerProfileController::class, 'update']
    )->whereNumber('id');

    Route::patch(
        '/profiles/{id}',
        [FreelancerProfileController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/profiles/{id}',
        [FreelancerProfileController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/services',
        [ServiceController::class, 'index']
    );

    Route::post(
        '/services',
        [ServiceController::class, 'store']
    );

    Route::get(
        '/services/{id}',
        [ServiceController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/services/{id}',
        [ServiceController::class, 'update']
    )->whereNumber('id');

    Route::patch(
        '/services/{id}',
        [ServiceController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/services/{id}',
        [ServiceController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Briefcases / Portfolios
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| Briefcases / Portfolios
|--------------------------------------------------------------------------
*/

    Route::get(
        '/briefcases',
        [BriefcaseController::class, 'index']
    );

    Route::get(
        '/briefcases/me',
        [BriefcaseController::class, 'myBriefcases']
    );

    Route::get(
        '/briefcases/freelancer/{freelancerId}',
        [BriefcaseController::class, 'byFreelancer']
    )->whereNumber('freelancerId');

    Route::post(
        '/briefcases',
        [BriefcaseController::class, 'store']
    );

    Route::get(
        '/briefcases/{id}',
        [BriefcaseController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/briefcases/{id}',
        [BriefcaseController::class, 'update']
    )->whereNumber('id');

    Route::patch(
        '/briefcases/{id}',
        [BriefcaseController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/briefcases/{id}',
        [BriefcaseController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Availabilities
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/availabilities',
        [AvailabilityController::class, 'index']
    );

    Route::post(
        '/availabilities',
        [AvailabilityController::class, 'store']
    );

    Route::get(
        '/availabilities/{id}',
        [AvailabilityController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/availabilities/{id}',
        [AvailabilityController::class, 'update']
    )->whereNumber('id');

    Route::patch(
        '/availabilities/{id}',
        [AvailabilityController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/availabilities/{id}',
        [AvailabilityController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Contract Requests
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/contract-requests',
        [ContractRequestController::class, 'index']
    );

    Route::post(
        '/contract-requests',
        [ContractRequestController::class, 'store']
    );

    Route::get(
        '/contract-requests/{id}',
        [ContractRequestController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/contract-requests/{id}',
        [ContractRequestController::class, 'update']
    )->whereNumber('id');

    Route::patch(
        '/contract-requests/{id}',
        [ContractRequestController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/contract-requests/{id}',
        [ContractRequestController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Contracts
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/contracts',
        [ContractController::class, 'index']
    );

    Route::post(
        '/contracts',
        [ContractController::class, 'store']
    );

    Route::get(
        '/contracts/{id}',
        [ContractController::class, 'show']
    )->whereNumber('id');

    Route::put(
        '/contracts/{id}',
        [ContractController::class, 'update']
    )->whereNumber('id');

    Route::patch(
        '/contracts/{id}',
        [ContractController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/contracts/{id}',
        [ContractController::class, 'destroy']
    )->whereNumber('id');
});
