<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\FreelancerProfileController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BriefcaseController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\ContractRequestController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\VacancyController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\LegalDocumentController;
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
*/

Route::prefix('public')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Legal Documents
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/legal/terms-and-conditions',
        [
            LegalDocumentController::class,
            'publicTermsAndConditions',
        ]
    );

    Route::get(
        '/legal/terms-and-conditions/pdf',
        [
            LegalDocumentController::class,
            'publicTermsAndConditionsPdf',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Public Freelancer Profiles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profiles',
        [
            FreelancerProfileController::class,
            'publicIndex',
        ]
    );

    Route::get(
        '/profiles/user/{userId}',
        [
            FreelancerProfileController::class,
            'publicShowByUserId',
        ]
    )->whereNumber('userId');

    Route::get(
        '/profiles/{id}',
        [
            FreelancerProfileController::class,
            'publicShow',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Public Company Profiles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/company-profiles',
        [
            CompanyProfileController::class,
            'publicIndex',
        ]
    );

    Route::get(
        '/company-profiles/{id}',
        [
            CompanyProfileController::class,
            'publicShow',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Public Services
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/services',
        [
            ServiceController::class,
            'publicIndex',
        ]
    );

    Route::get(
        '/services/{id}',
        [
            ServiceController::class,
            'publicShow',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Public Briefcases / Portfolios
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/briefcases',
        [
            BriefcaseController::class,
            'publicIndex',
        ]
    );

    Route::get(
        '/briefcases/freelancer/{freelancerId}',
        [
            BriefcaseController::class,
            'publicByFreelancer',
        ]
    )->whereNumber('freelancerId');

    Route::get(
        '/briefcases/{id}',
        [
            BriefcaseController::class,
            'publicShow',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Public Vacancies
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/vacancies',
        [
            VacancyController::class,
            'publicIndex',
        ]
    );

    Route::get(
        '/vacancies/company/{companyId}',
        [
            VacancyController::class,
            'publicByCompany',
        ]
    )->whereNumber('companyId');

    Route::get(
        '/vacancies/{id}',
        [
            VacancyController::class,
            'publicShow',
        ]
    )->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | Public Reviews
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reviews/user/{userId}',
        [ReviewController::class, 'publicByUser']
    )->whereNumber('userId');

    Route::get(
        '/reviews/freelancer/{freelancerId}',
        [ReviewController::class, 'publicByFreelancer']
    )->whereNumber('freelancerId');

    Route::get(
        '/reviews/company/{companyId}',
        [ReviewController::class, 'publicByCompany']
    )->whereNumber('companyId');
});


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth.api')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::post(
        '/chatbot/auth-message',
        [
            ChatBotController::class,
            'sendAuthMessage',
        ]
    )->middleware('throttle:30,1');


    /*
    |--------------------------------------------------------------------------
    | My Account
    |--------------------------------------------------------------------------
    */

    Route::patch(
        '/users/me',
        [
            UserController::class,
            'updateMe',
        ]
    );

    Route::delete(
        '/users/me',
        [
            UserController::class,
            'destroyMe',
        ]
    );

    Route::post(
        '/users/me/profile-photo',
        [
            UserController::class,
            'updateMyProfilePhoto',
        ]
    );

    Route::delete(
        '/users/me/profile-photo',
        [
            UserController::class,
            'destroyMyProfilePhoto',
        ]
    );


    /*
    |--------------------------------------------------------------------------
    | Activity Logs - Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get(
            '/activity-logs/summary',
            [
                ActivityLogController::class,
                'summary',
            ]
        );

        Route::get(
            '/activity-logs',
            [
                ActivityLogController::class,
                'index',
            ]
        );
    });


    /*
    |--------------------------------------------------------------------------
    | Roles - Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get(
            '/roles',
            [
                RoleController::class,
                'index',
            ]
        );

        Route::post(
            '/roles',
            [
                RoleController::class,
                'store',
            ]
        );

        Route::get(
            '/roles/{id}',
            [
                RoleController::class,
                'show',
            ]
        )->whereNumber('id');

        Route::put(
            '/roles/{id}',
            [
                RoleController::class,
                'update',
            ]
        )->whereNumber('id');

        Route::delete(
            '/roles/{id}',
            [
                RoleController::class,
                'destroy',
            ]
        )->whereNumber('id');
    });


    /*
    |--------------------------------------------------------------------------
    | User Administration - Admin
    |--------------------------------------------------------------------------
    */

    Route::middleware('role:admin')->group(function () {
        Route::get(
            '/users',
            [
                UserController::class,
                'index',
            ]
        );

        Route::post(
            '/users',
            [
                UserController::class,
                'store',
            ]
        );

        Route::get(
            '/users/{id}',
            [
                UserController::class,
                'show',
            ]
        )->whereNumber('id');

        Route::patch(
            '/users/{id}',
            [
                UserController::class,
                'update',
            ]
        )->whereNumber('id');

        Route::delete(
            '/users/{id}',
            [
                UserController::class,
                'destroy',
            ]
        )->whereNumber('id');
    });


    /*
    |--------------------------------------------------------------------------
    | Freelancer Profiles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/profiles',
        [
            FreelancerProfileController::class,
            'index',
        ]
    );

    Route::post(
        '/profiles',
        [
            FreelancerProfileController::class,
            'store',
        ]
    );

    Route::get(
        '/profiles/user/{userId}',
        [
            FreelancerProfileController::class,
            'showByUserId',
        ]
    )->whereNumber('userId');

    Route::get(
        '/profiles/{id}',
        [
            FreelancerProfileController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::put(
        '/profiles/{id}',
        [
            FreelancerProfileController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/profiles/{id}',
        [
            FreelancerProfileController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Company Profiles
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/company-profiles',
        [
            CompanyProfileController::class,
            'index',
        ]
    );

    Route::get(
        '/company-profiles/me',
        [
            CompanyProfileController::class,
            'myProfile',
        ]
    );

    Route::post(
        '/company-profiles',
        [
            CompanyProfileController::class,
            'store',
        ]
    );

    Route::get(
        '/company-profiles/{id}',
        [
            CompanyProfileController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::patch(
        '/company-profiles/{id}',
        [
            CompanyProfileController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/company-profiles/{id}',
        [
            CompanyProfileController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/services',
        [
            ServiceController::class,
            'index',
        ]
    );

    Route::post(
        '/services',
        [
            ServiceController::class,
            'store',
        ]
    );

    Route::get(
        '/services/freelancer/{freelancerId}',
        [
            ServiceController::class,
            'byFreelancer',
        ]
    )->whereNumber('freelancerId');

    Route::get(
        '/services/{id}',
        [
            ServiceController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::put(
        '/services/{id}',
        [
            ServiceController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/services/{id}',
        [
            ServiceController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Briefcases / Portfolios
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/briefcases',
        [
            BriefcaseController::class,
            'index',
        ]
    );

    Route::get(
        '/briefcases/me',
        [
            BriefcaseController::class,
            'myBriefcases',
        ]
    );

    Route::get(
        '/briefcases/freelancer/{freelancerId}',
        [
            BriefcaseController::class,
            'byFreelancer',
        ]
    )->whereNumber('freelancerId');

    Route::post(
        '/briefcases',
        [
            BriefcaseController::class,
            'store',
        ]
    );

    Route::post(
        '/briefcases/{id}/image',
        [
            BriefcaseController::class,
            'updateImage',
        ]
    )->whereNumber('id');

    Route::delete(
        '/briefcases/{id}/image',
        [
            BriefcaseController::class,
            'destroyImage',
        ]
    )->whereNumber('id');

    Route::put(
        '/briefcases/{id}',
        [
            BriefcaseController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::get(
        '/briefcases/{id}',
        [
            BriefcaseController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::delete(
        '/briefcases/{id}',
        [
            BriefcaseController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Availabilities
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/availabilities',
        [
            AvailabilityController::class,
            'index',
        ]
    );

    Route::post(
        '/availabilities',
        [
            AvailabilityController::class,
            'store',
        ]
    );

    Route::get(
        '/availabilities/{id}',
        [
            AvailabilityController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::patch(
        '/availabilities/{id}',
        [
            AvailabilityController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/availabilities/{id}',
        [
            AvailabilityController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Contract Requests
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/contract-requests',
        [
            ContractRequestController::class,
            'index',
        ]
    );

    Route::post(
        '/contract-requests',
        [
            ContractRequestController::class,
            'store',
        ]
    );

    Route::get(
        '/contract-requests/{id}',
        [
            ContractRequestController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::patch(
        '/contract-requests/{id}',
        [
            ContractRequestController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/contract-requests/{id}',
        [
            ContractRequestController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Contracts
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/contracts',
        [
            ContractController::class,
            'index',
        ]
    );

    Route::post(
        '/contracts',
        [
            ContractController::class,
            'store',
        ]
    );

    Route::get(
        '/contracts/{id}',
        [
            ContractController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::patch(
        '/contracts/{id}',
        [
            ContractController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/contracts/{id}',
        [
            ContractController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Vacancies
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/vacancies',
        [
            VacancyController::class,
            'index',
        ]
    );

    Route::get(
        '/vacancies/me',
        [
            VacancyController::class,
            'myVacancies',
        ]
    );

    Route::post(
        '/vacancies',
        [
            VacancyController::class,
            'store',
        ]
    );

    Route::get(
        '/vacancies/{id}',
        [
            VacancyController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::patch(
        '/vacancies/{id}',
        [
            VacancyController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/vacancies/{id}',
        [
            VacancyController::class,
            'destroy',
        ]
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Applications
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/applications',
        [
            ApplicationController::class,
            'index',
        ]
    );

    Route::get(
        '/applications/me',
        [
            ApplicationController::class,
            'myApplications',
        ]
    );

    Route::get(
        '/applications/vacancy/{vacancyId}',
        [
            ApplicationController::class,
            'byVacancy',
        ]
    )->whereNumber('vacancyId');

    Route::post(
        '/applications',
        [
            ApplicationController::class,
            'store',
        ]
    );

    Route::get(
        '/availabilities/me',
        [AvailabilityController::class, 'myAvailabilities']
    );

    Route::get(
        '/applications/{id}',
        [
            ApplicationController::class,
            'show',
        ]
    )->whereNumber('id');

    Route::patch(
        '/applications/{id}',
        [
            ApplicationController::class,
            'update',
        ]
    )->whereNumber('id');

    Route::delete(
        '/applications/{id}',
        [
            ApplicationController::class,
            'destroy',
        ]
    )->whereNumber('id');

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/messages/conversations',
        [MessageController::class, 'conversations']
    );

    Route::get(
        '/messages/conversation/{userId}',
        [MessageController::class, 'conversation']
    )->whereNumber('userId');

    Route::post(
        '/messages',
        [MessageController::class, 'store']
    );

    Route::patch(
        '/messages/read-all/{userId}',
        [MessageController::class, 'markConversationAsRead']
    )->whereNumber('userId');

    Route::patch(
        '/messages/{id}/read',
        [MessageController::class, 'markAsRead']
    )->whereNumber('id');

    Route::delete(
        '/messages/{id}',
        [MessageController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Reviews
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reviews',
        [ReviewController::class, 'index']
    );

    Route::post(
        '/reviews',
        [ReviewController::class, 'store']
    );

    Route::get(
        '/reviews/{id}',
        [ReviewController::class, 'show']
    )->whereNumber('id');

    Route::patch(
        '/reviews/{id}',
        [ReviewController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/reviews/{id}',
        [ReviewController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notifications',
        [NotificationController::class, 'index']
    );

    Route::get(
        '/notifications/unread-count',
        [NotificationController::class, 'unreadCount']
    );

    Route::patch(
        '/notifications/read-all',
        [NotificationController::class, 'markAllAsRead']
    );

    Route::patch(
        '/notifications/{id}/read',
        [NotificationController::class, 'markAsRead']
    )->whereNumber('id');

    Route::delete(
        '/notifications/{id}',
        [NotificationController::class, 'destroy']
    )->whereNumber('id');


    /*
    |--------------------------------------------------------------------------
    | Reports - Trust and Safety
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/reports/summary',
        [ReportController::class, 'summary']
    );

    Route::get(
        '/reports',
        [ReportController::class, 'index']
    );

    Route::post(
        '/reports',
        [ReportController::class, 'store']
    );

    Route::get(
        '/reports/{id}',
        [ReportController::class, 'show']
    )->whereNumber('id');

    Route::patch(
        '/reports/{id}',
        [ReportController::class, 'update']
    )->whereNumber('id');

    Route::delete(
        '/reports/{id}',
        [ReportController::class, 'destroy']
    )->whereNumber('id');
});


/*
|--------------------------------------------------------------------------
| CORS Preflight
|--------------------------------------------------------------------------
*/

Route::options('{any}', function () {
    return response('', 200)
        ->header(
            'Access-Control-Allow-Origin',
            '*'
        )
        ->header(
            'Access-Control-Allow-Methods',
            'GET, POST, PUT, PATCH, DELETE, OPTIONS'
        )
        ->header(
            'Access-Control-Allow-Headers',
            'Content-Type, Authorization, X-Requested-With'
        );
})->where('any', '.*');
