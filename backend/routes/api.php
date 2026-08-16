<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminEventDetailsController;
use App\Http\Controllers\AdminEventTitleController;
use App\Http\Controllers\AdminGraduationYearRangeController;
use App\Http\Controllers\AdminEventLogoController;
use App\Http\Controllers\AdminInviteController;
use App\Http\Controllers\AdminPhaseController;
use App\Http\Controllers\AdminPhaseDatesController;
use App\Http\Controllers\AdminSeriesController;
use App\Http\Controllers\AdminSlotOptionController;
use App\Http\Controllers\AdminStudentImportController;
use App\Http\Controllers\AdminTagController;
use App\Http\Controllers\AdminTopicController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResendVerificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\AppConfigController;
use App\Http\Controllers\Auth\ConsultantLoginController;
use App\Http\Controllers\Auth\StudentLoginController;
use App\Http\Controllers\ConsultantProfileController;
use App\Http\Controllers\ConsultantSessionController;
use App\Http\Controllers\SeriesController;
use App\Http\Controllers\SlotOptionController;
use App\Http\Controllers\StudentSelectionController;
use App\Http\Controllers\StudentTagController;
use App\Http\Controllers\StudentTopicController;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireStudent;
use Illuminate\Support\Facades\Route;

// Public app configuration (LDAP flags, phase, limits)
Route::get('config', [AppConfigController::class, 'show']);

// Public series list (speaker profile picker + admin management page)
Route::get('series', [SeriesController::class, 'index']);

// Public slot options list (speaker session slot picker + admin management page)
Route::get('slot-options', [SlotOptionController::class, 'index']);

// Registration and email verification
Route::post('auth/register', [RegisterController::class, 'register']);
Route::post('auth/invitation/accept', [AcceptInvitationController::class, 'accept']);
Route::get('auth/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])->name('verification.verify');
Route::post('auth/email/resend', [ResendVerificationController::class, 'resend']);
Route::post('auth/consultant/forgot-password', [ForgotPasswordController::class, 'sendResetLink']);

// Admin auth (email + password only — never LDAP; kept fully separate from the consultant/student
// login endpoints, which now reject admin accounts outright)
Route::prefix('auth/admin')->group(function () {
    Route::post('login', [AdminLoginController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AdminLoginController::class, 'logout']);
        Route::get('me', [AdminLoginController::class, 'me']);
    });
});

// Consultant auth (email + password, or LDAP when ldap_consultants=true)
Route::prefix('auth/consultant')->group(function () {
    Route::post('login', [ConsultantLoginController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [ConsultantLoginController::class, 'logout']);
        Route::get('me', [ConsultantLoginController::class, 'me']);
    });
});

// Consultant profile (view + update, including photo upload)
Route::prefix('consultant')->middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ConsultantProfileController::class, 'show']);
    Route::post('profile', [ConsultantProfileController::class, 'update']);
    Route::get('session', [ConsultantSessionController::class, 'show']);
    Route::post('session', [ConsultantSessionController::class, 'update']);
});

// Admin-only endpoints
Route::prefix('admin')->middleware(['auth:sanctum', RequireAdmin::class])->group(function () {
    Route::get('students', [AdminController::class, 'students']);
    Route::post('students/import', [AdminStudentImportController::class, 'import']);
    Route::get('consultants', [AdminController::class, 'consultants']);
    Route::get('consultants/{id}', [AdminController::class, 'consultant']);
    Route::get('topics', [AdminController::class, 'topics']);
    Route::get('tags', [AdminController::class, 'tags']);
    Route::post('phase', [AdminPhaseController::class, 'update']);
    Route::post('phase-dates', [AdminPhaseDatesController::class, 'update']);
    Route::post('event-title', [AdminEventTitleController::class, 'update']);
    Route::post('event-details', [AdminEventDetailsController::class, 'update']);
    Route::post('event-logo', [AdminEventLogoController::class, 'update']);
    Route::delete('event-logo', [AdminEventLogoController::class, 'destroy']);
    Route::post('graduation-year-range', [AdminGraduationYearRangeController::class, 'update']);
    Route::post('invite', [AdminInviteController::class, 'invite']);
    Route::post('invite/bulk', [AdminInviteController::class, 'bulkInvite']);
    Route::post('series', [AdminSeriesController::class, 'store']);
    Route::put('series/{series}', [AdminSeriesController::class, 'update']);
    Route::delete('series/{series}', [AdminSeriesController::class, 'destroy']);
    Route::post('slot-options', [AdminSlotOptionController::class, 'store']);
    Route::put('slot-options/{slotOption}', [AdminSlotOptionController::class, 'update']);
    Route::delete('slot-options/{slotOption}', [AdminSlotOptionController::class, 'destroy']);
    Route::post('tags', [AdminTagController::class, 'store']);
    Route::delete('tags/{tag}', [AdminTagController::class, 'destroy']);
    Route::post('topics/{topic}/tag', [AdminTopicController::class, 'updateTag']);
});

// Student auth (always via LDAP username + password)
Route::prefix('auth/student')->group(function () {
    Route::post('login', [StudentLoginController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [StudentLoginController::class, 'logout']);
        Route::get('me', [StudentLoginController::class, 'me']);
    });
});

// Student topic browsing + tag selection (selection phase)
Route::prefix('student')->middleware(['auth:sanctum', RequireStudent::class])->group(function () {
    Route::get('topics', [StudentTopicController::class, 'index']);
    Route::get('tags', [StudentTagController::class, 'index']);
    Route::get('selection', [StudentSelectionController::class, 'show']);
    Route::post('selection', [StudentSelectionController::class, 'update']);
});
