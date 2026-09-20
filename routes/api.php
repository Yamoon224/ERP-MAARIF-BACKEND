<?php

use App\Domains\Academics\Http\Controllers\SchoolClassController;
use App\Domains\Academics\Http\Controllers\SubjectController;
use App\Domains\Academics\Http\Controllers\TermController;
use App\Domains\Attendance\Http\Controllers\AttendanceController;
use App\Domains\Auth\Http\Controllers\ParentAuthController;
use App\Domains\Auth\Http\Controllers\StaffAuthController;
use App\Domains\Discipline\Http\Controllers\SanctionController;
use App\Domains\Discipline\Http\Controllers\SummonController;
use App\Domains\Grades\Http\Controllers\BulletinController;
use App\Domains\Grades\Http\Controllers\GradeController;
use App\Domains\Notifications\Http\Controllers\NotificationLogController;
use App\Domains\Shared\Http\Controllers\HealthController;
use App\Domains\Students\Http\Controllers\StudentController;
use App\Domains\Users\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API REST — ERP Maarif
|--------------------------------------------------------------------------
|
| Trois zones :
|
|   1. **Publique** — connexions. Le personnel se connecte par e-mail, le
|      parent par le matricule de son enfant (cahier des charges 3.1).
|
|   2. **Personnel** — administration et enseignement. Jeton Sanctum,
|      cloisonne par `account_type:staff` puis par permission Spatie.
|
|   3. **Portail parent** — lecture seule des informations d'un seul eleve,
|      celui dont le jeton a ete emis (`account_type:parent`). Aucune route
|      de ce groupe ne lit d'identifiant d'eleve dans la requete : c'est
|      toujours `$request->user()` qui le fournit (voir les methodes `mine`
|      des controleurs).
|
*/

Route::get('/health', HealthController::class);

Route::post('/login', [StaffAuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/parent/login', [ParentAuthController::class, 'login'])->middleware('throttle:6,1');

// =============================================================================
// Personnel (administrateurs, enseignants)
// =============================================================================
Route::middleware(['auth:sanctum', 'account_type:staff'])->group(function (): void {
    Route::post('/logout', [StaffAuthController::class, 'logout']);
    Route::get('/me', [StaffAuthController::class, 'me']);

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::apiResource('users', UserController::class);
    });

    // Consultation ouverte a tout le personnel : necessaire aux ecrans de
    // saisie de notes et de presences (choix de la classe, de la matiere, du
    // trimestre). Seule la modification de la structure est reservee a
    // `academics.manage`.
    Route::middleware('permission:academics.view')->group(function (): void {
        Route::get('/classes', [SchoolClassController::class, 'index']);
        Route::get('/classes/{schoolClass}', [SchoolClassController::class, 'show']);
        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::get('/subjects/{subject}', [SubjectController::class, 'show']);
        Route::get('/terms', [TermController::class, 'all']);
        Route::get('/terms/paginated', [TermController::class, 'index']);
        Route::get('/terms/{term}', [TermController::class, 'show']);
    });
    Route::middleware('permission:academics.manage')->group(function (): void {
        Route::apiResource('classes', SchoolClassController::class)
            ->parameters(['classes' => 'schoolClass'])
            ->except(['index', 'show']);
        Route::apiResource('subjects', SubjectController::class)->except(['index', 'show']);
        Route::apiResource('terms', TermController::class)->except(['index', 'show']);
    });

    Route::middleware('permission:students.view')->group(function (): void {
        Route::get('/students', [StudentController::class, 'index']);
        Route::get('/students/{student}', [StudentController::class, 'show']);
        Route::get('/students/{student}/bulletin', [BulletinController::class, 'forStudent']);
    });
    Route::middleware('permission:students.manage')->group(function (): void {
        Route::post('/students', [StudentController::class, 'store']);
        Route::put('/students/{student}', [StudentController::class, 'update']);
        Route::patch('/students/{student}', [StudentController::class, 'update']);
        Route::delete('/students/{student}', [StudentController::class, 'destroy']);
        Route::post('/students/{student}/reset-password', [StudentController::class, 'resetPassword']);
    });

    Route::middleware('permission:grades.manage')->group(function (): void {
        Route::apiResource('grades', GradeController::class);
    });

    Route::middleware('permission:attendance.manage')->group(function (): void {
        Route::apiResource('attendance-records', AttendanceController::class)
            ->except(['show'])
            ->parameters(['attendance-records' => 'attendanceRecord']);
    });

    Route::middleware('permission:discipline.manage')->group(function (): void {
        Route::apiResource('summons', SummonController::class);
        Route::apiResource('sanctions', SanctionController::class);
    });

    Route::middleware('permission:notifications.view')->group(function (): void {
        Route::get('/notification-logs', [NotificationLogController::class, 'index']);
    });
});

// =============================================================================
// Portail parent — lecture seule, limitee a l'eleve du jeton
// =============================================================================
Route::middleware(['auth:sanctum', 'account_type:parent'])->group(function (): void {
    Route::post('/parent/logout', [ParentAuthController::class, 'logout']);
    Route::get('/parent/me', [ParentAuthController::class, 'me']);
    Route::get('/parent/bulletin', [BulletinController::class, 'mine']);
    Route::get('/parent/attendance', [AttendanceController::class, 'mine']);
    Route::get('/parent/summons', [SummonController::class, 'mine']);
    Route::get('/parent/sanctions', [SanctionController::class, 'mine']);
});
