<?php

namespace App\Providers;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Academics\Contracts\SubjectRepositoryContract;
use App\Domains\Academics\Contracts\TermRepositoryContract;
use App\Domains\Academics\Repositories\EloquentSchoolClassRepository;
use App\Domains\Academics\Repositories\EloquentSubjectRepository;
use App\Domains\Academics\Repositories\EloquentTermRepository;
use App\Domains\Accounting\Contracts\AccountingReportRepositoryContract;
use App\Domains\Accounting\Contracts\InstallmentRepositoryContract;
use App\Domains\Accounting\Contracts\PaymentRepositoryContract;
use App\Domains\Accounting\Observers\EnrollmentTuitionObserver;
use App\Domains\Accounting\Observers\SchoolClassFeeObserver;
use App\Domains\Accounting\Observers\TermCalendarObserver;
use App\Domains\Accounting\Repositories\EloquentAccountingReportRepository;
use App\Domains\Accounting\Repositories\EloquentInstallmentRepository;
use App\Domains\Accounting\Repositories\EloquentPaymentRepository;
use App\Domains\Admissions\Contracts\AdmissionRepositoryContract;
use App\Domains\Admissions\Repositories\EloquentAdmissionRepository;
use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Attendance\Repositories\EloquentAttendanceRepository;
use App\Domains\Discipline\Contracts\SanctionRepositoryContract;
use App\Domains\Discipline\Contracts\SummonRepositoryContract;
use App\Domains\Discipline\Repositories\EloquentSanctionRepository;
use App\Domains\Discipline\Repositories\EloquentSummonRepository;
use App\Domains\Expenses\Contracts\ExpenseCategoryRepositoryContract;
use App\Domains\Expenses\Contracts\ExpenseReportRepositoryContract;
use App\Domains\Expenses\Contracts\ExpenseRepositoryContract;
use App\Domains\Expenses\Repositories\EloquentExpenseCategoryRepository;
use App\Domains\Expenses\Repositories\EloquentExpenseReportRepository;
use App\Domains\Expenses\Repositories\EloquentExpenseRepository;
use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Grades\Repositories\EloquentGradeRepository;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Domains\Notifications\Senders\LogNotificationSender;
use App\Domains\Results\Contracts\PromotionDecisionRepositoryContract;
use App\Domains\Results\Repositories\EloquentPromotionDecisionRepository;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Domains\Students\Contracts\StudentRepositoryContract;
use App\Domains\Students\Observers\StudentEnrollmentObserver;
use App\Domains\Students\Repositories\EloquentEnrollmentRepository;
use App\Domains\Students\Repositories\EloquentStudentRepository;
use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Repositories\EloquentUserRepository;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Support\ServiceProvider;

/**
 * Point unique de cablage entre contrats et implementations (inversion des
 * dependances) : aucun service metier ne reference une classe concrete de
 * persistance ou d'envoi de notification, tout passe par les interfaces
 * listees ici. C'est ce qui permet de substituer une implementation en test
 * (voir App\Domains\Notifications\Senders\ArrayNotificationSender) sans
 * toucher au code metier.
 */
class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryContract::class => EloquentUserRepository::class,
        StudentRepositoryContract::class => EloquentStudentRepository::class,
        EnrollmentRepositoryContract::class => EloquentEnrollmentRepository::class,

        SchoolClassRepositoryContract::class => EloquentSchoolClassRepository::class,
        SubjectRepositoryContract::class => EloquentSubjectRepository::class,
        TermRepositoryContract::class => EloquentTermRepository::class,

        GradeRepositoryContract::class => EloquentGradeRepository::class,
        AttendanceRepositoryContract::class => EloquentAttendanceRepository::class,

        SummonRepositoryContract::class => EloquentSummonRepository::class,
        SanctionRepositoryContract::class => EloquentSanctionRepository::class,

        PaymentRepositoryContract::class => EloquentPaymentRepository::class,
        InstallmentRepositoryContract::class => EloquentInstallmentRepository::class,
        AccountingReportRepositoryContract::class => EloquentAccountingReportRepository::class,

        ExpenseRepositoryContract::class => EloquentExpenseRepository::class,
        ExpenseCategoryRepositoryContract::class => EloquentExpenseCategoryRepository::class,
        ExpenseReportRepositoryContract::class => EloquentExpenseReportRepository::class,

        AdmissionRepositoryContract::class => EloquentAdmissionRepository::class,
        PromotionDecisionRepositoryContract::class => EloquentPromotionDecisionRepository::class,
    ];

    public function register(): void
    {
        $this->registerNotificationSender();
    }

    public function boot(): void
    {
        // Un eleve est toujours inscrit pour l'annee de sa classe (voir
        // StudentEnrollmentObserver).
        Student::observe(StudentEnrollmentObserver::class);

        // Et l'echeancier de scolarite d'une inscription suit son inscription
        // et le calendrier des trimestres (voir TuitionService).
        Enrollment::observe(EnrollmentTuitionObserver::class);
        Term::observe(TermCalendarObserver::class);
        SchoolClass::observe(SchoolClassFeeObserver::class);
    }

    /**
     * Pilote d'envoi des notifications, choisi par configuration.
     *
     * Enregistre en singleton : le pilote de test conserve les messages en
     * memoire, et une seconde instance ferait inspecter a un test un envoi
     * qui n'a jamais eu lieu.
     */
    private function registerNotificationSender(): void
    {
        $this->app->singleton(NotificationSenderContract::class, function (): NotificationSenderContract {
            $driver = config('notifications.driver', 'log');

            return match ($driver) {
                'array' => $this->app->make(ArrayNotificationSender::class),
                default => $this->app->make(LogNotificationSender::class),
            };
        });
    }
}
