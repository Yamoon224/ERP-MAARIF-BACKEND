<?php

use App\Domains\Academics\Http\Controllers\AcademicYearController;
use App\Domains\Academics\Http\Controllers\SchoolClassController;
use App\Domains\Academics\Http\Controllers\SubjectController;
use App\Domains\Academics\Http\Controllers\TermController;
use App\Domains\Accounting\Http\Controllers\AccountingReportController;
use App\Domains\Accounting\Http\Controllers\FeeController;
use App\Domains\Accounting\Http\Controllers\PaymentController;
use App\Domains\Accounting\Http\Controllers\TuitionController;
use App\Domains\Admissions\Http\Controllers\AdmissionController;
use App\Domains\Attendance\Http\Controllers\AttendanceController;
use App\Domains\Auth\Http\Controllers\ParentAuthController;
use App\Domains\Auth\Http\Controllers\StaffAuthController;
use App\Domains\Discipline\Http\Controllers\SanctionController;
use App\Domains\Discipline\Http\Controllers\SummonController;
use App\Domains\Grades\Http\Controllers\BulletinController;
use App\Domains\Grades\Http\Controllers\GradeController;
use App\Domains\Notifications\Http\Controllers\NotificationLogController;
use App\Domains\Reporting\Http\Controllers\DashboardController;
use App\Domains\Reporting\Http\Controllers\TermOverviewController;
use App\Domains\Results\Http\Controllers\ResultsController;
use App\Domains\Shared\Http\Controllers\HealthController;
use App\Domains\Students\Http\Controllers\EnrollmentController;
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
| Filtres de periode : les listes et indicateurs acceptent `academic_year`
| (2025-2026), `term_id` et `month` (2025-11). Voir App\Domains\Shared\Support\Period.
|
*/

Route::get('/health', HealthController::class);

Route::post('/login', [StaffAuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/parent/login', [ParentAuthController::class, 'login'])->middleware('throttle:6,1');

// =============================================================================
// Personnel (administrateurs, enseignants, comptables)
// =============================================================================
Route::middleware(['auth:sanctum', 'account_type:staff'])->group(function (): void {
    Route::post('/logout', [StaffAuthController::class, 'logout']);
    Route::get('/me', [StaffAuthController::class, 'me']);
    Route::put('/me', [StaffAuthController::class, 'updateProfile']);
    Route::put('/me/password', [StaffAuthController::class, 'changePassword']);

    // Tableau de bord : ouvert a tout le personnel, mais les blocs discipline
    // et comptabilite ne sont remplis que pour qui en a le droit (voir
    // DashboardController).
    Route::get('/dashboard', DashboardController::class);

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::apiResource('users', UserController::class);
    });

    // Consultation ouverte a tout le personnel : necessaire aux ecrans de
    // saisie de notes et de presences (choix de la classe, de la matiere, du
    // trimestre). Seule la modification de la structure est reservee a
    // `academics.manage`.
    Route::middleware('permission:academics.view')->group(function (): void {
        Route::get('/academic-years', [AcademicYearController::class, 'index']);
        Route::get('/classes', [SchoolClassController::class, 'index']);
        Route::get('/classes/{schoolClass}', [SchoolClassController::class, 'show']);
        Route::get('/subjects', [SubjectController::class, 'index']);
        Route::get('/subjects/{subject}', [SubjectController::class, 'show']);
        Route::get('/terms', [TermController::class, 'all']);
        Route::get('/terms/paginated', [TermController::class, 'index']);
        Route::get('/terms/{term}', [TermController::class, 'show']);
        Route::get('/terms/{term}/overview', [TermOverviewController::class, 'summary']);
        Route::get('/terms/{term}/subjects', [TermOverviewController::class, 'subjects']);
        Route::get('/terms/{term}/students', [TermOverviewController::class, 'students']);
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
        Route::get('/students/{student}/enrollments', [EnrollmentController::class, 'index']);
    });
    Route::middleware('permission:students.manage')->group(function (): void {
        Route::post('/students', [StudentController::class, 'store']);
        Route::put('/students/{student}', [StudentController::class, 'update']);
        Route::patch('/students/{student}', [StudentController::class, 'update']);
        Route::delete('/students/{student}', [StudentController::class, 'destroy']);
        Route::post('/students/{student}/reset-password', [StudentController::class, 'resetPassword']);
        Route::post('/students/{student}/enrollments', [EnrollmentController::class, 'store']);
    });

    Route::middleware('permission:results.view')->group(function (): void {
        Route::get('/results', [ResultsController::class, 'forClass']);
        Route::get('/students/{student}/results', [ResultsController::class, 'forStudent']);
    });
    Route::middleware('permission:results.manage')->group(function (): void {
        Route::put('/enrollments/{enrollment}/decision', [ResultsController::class, 'saveDecision']);
        Route::post('/classes/{schoolClass}/decisions/validate', [ResultsController::class, 'validateDecisions']);
        Route::post('/classes/{schoolClass}/promotions', [ResultsController::class, 'promote']);
    });

    Route::middleware('permission:admissions.view')->group(function (): void {
        Route::get('/admissions/summary', [AdmissionController::class, 'summary']);
        Route::get('/admissions', [AdmissionController::class, 'index']);
        Route::get('/admissions/{admission}', [AdmissionController::class, 'show']);
    });
    Route::middleware('permission:admissions.manage')->group(function (): void {
        Route::post('/admissions', [AdmissionController::class, 'store']);
        Route::put('/admissions/{admission}', [AdmissionController::class, 'update']);
        Route::patch('/admissions/{admission}', [AdmissionController::class, 'update']);
        Route::delete('/admissions/{admission}', [AdmissionController::class, 'destroy']);
        Route::post('/admissions/{admission}/status', [AdmissionController::class, 'changeStatus']);
        Route::post('/admissions/{admission}/enroll', [AdmissionController::class, 'enroll']);
    });

    Route::middleware('permission:grades.manage')->group(function (): void {
        Route::apiResource('grades', GradeController::class);
    });

    Route::middleware('permission:attendance.manage')->group(function (): void {
        Route::get('/attendance-records/summary', [AttendanceController::class, 'summary']);
        Route::get('/attendance-records/roll-call', [AttendanceController::class, 'rollCall']);
        Route::post('/attendance-records/bulk', [AttendanceController::class, 'storeBulk']);
        Route::apiResource('attendance-records', AttendanceController::class)
            ->except(['show'])
            ->parameters(['attendance-records' => 'attendanceRecord']);
    });

    Route::middleware('permission:discipline.manage')->group(function (): void {
        Route::apiResource('summons', SummonController::class);
        Route::apiResource('sanctions', SanctionController::class);
    });

    // Comptabilite : la consultation (paiements, releves, impayes) est
    // separee de l'encaissement, pour qu'un compte en lecture seule puisse
    // auditer sans pouvoir creer ni annuler un recu.
    Route::middleware('permission:accounting.view')->group(function (): void {
        Route::get('/payments', [PaymentController::class, 'index']);
        Route::get('/payments/{payment}', [PaymentController::class, 'show']);
        Route::get('/enrollments/{enrollment}/tuition', [TuitionController::class, 'show']);
        Route::get('/enrollments/{enrollment}/payment-preview', [TuitionController::class, 'preview']);
        Route::get('/accounting/summary', [AccountingReportController::class, 'summary']);
        Route::get('/accounting/arrears', [AccountingReportController::class, 'arrears']);
    });
    Route::middleware('permission:accounting.manage')->group(function (): void {
        Route::post('/payments', [PaymentController::class, 'store']);
        Route::post('/payments/{payment}/cancel', [PaymentController::class, 'cancel']);
        Route::put('/classes/{schoolClass}/fee', [FeeController::class, 'update']);
    });

    Route::middleware('permission:notifications.view')->group(function (): void {
        Route::get('/notification-logs', [NotificationLogController::class, 'index']);
        Route::get('/notification-logs/summary', [NotificationLogController::class, 'summary']);
    });
    Route::middleware('permission:notifications.manage')->group(function (): void {
        Route::post('/notification-logs/{notificationLog}/resend', [NotificationLogController::class, 'resend']);
    });
});

// =============================================================================
// Portail parent — lecture seule, limitee a l'eleve du jeton
// =============================================================================
Route::middleware(['auth:sanctum', 'account_type:parent'])->group(function (): void {
    Route::post('/parent/logout', [ParentAuthController::class, 'logout']);
    Route::get('/parent/me', [ParentAuthController::class, 'me']);
    // Calendrier scolaire (annees et trimestres) : information generale de
    // l'etablissement, necessaire aux filtres de periode du portail.
    Route::get('/parent/academic-years', [AcademicYearController::class, 'index']);
    Route::put('/parent/me/password', [ParentAuthController::class, 'changePassword']);
    Route::get('/parent/bulletin', [BulletinController::class, 'mine']);
    Route::get('/parent/results', [ResultsController::class, 'mine']);
    Route::get('/parent/attendance', [AttendanceController::class, 'mine']);
    Route::get('/parent/summons', [SummonController::class, 'mine']);
    Route::get('/parent/sanctions', [SanctionController::class, 'mine']);
    Route::get('/parent/tuition', [TuitionController::class, 'mine']);
    Route::get('/parent/payments', [PaymentController::class, 'mine']);
});
