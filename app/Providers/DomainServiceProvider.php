<?php

namespace App\Providers;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Academics\Contracts\SubjectRepositoryContract;
use App\Domains\Academics\Contracts\TermRepositoryContract;
use App\Domains\Academics\Repositories\EloquentSchoolClassRepository;
use App\Domains\Academics\Repositories\EloquentSubjectRepository;
use App\Domains\Academics\Repositories\EloquentTermRepository;
use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Attendance\Repositories\EloquentAttendanceRepository;
use App\Domains\Discipline\Contracts\SanctionRepositoryContract;
use App\Domains\Discipline\Contracts\SummonRepositoryContract;
use App\Domains\Discipline\Repositories\EloquentSanctionRepository;
use App\Domains\Discipline\Repositories\EloquentSummonRepository;
use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Grades\Repositories\EloquentGradeRepository;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\Senders\ArrayNotificationSender;
use App\Domains\Notifications\Senders\LogNotificationSender;
use App\Domains\Students\Contracts\StudentRepositoryContract;
use App\Domains\Students\Repositories\EloquentStudentRepository;
use App\Domains\Users\Contracts\UserRepositoryContract;
use App\Domains\Users\Repositories\EloquentUserRepository;
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

        SchoolClassRepositoryContract::class => EloquentSchoolClassRepository::class,
        SubjectRepositoryContract::class => EloquentSubjectRepository::class,
        TermRepositoryContract::class => EloquentTermRepository::class,

        GradeRepositoryContract::class => EloquentGradeRepository::class,
        AttendanceRepositoryContract::class => EloquentAttendanceRepository::class,

        SummonRepositoryContract::class => EloquentSummonRepository::class,
        SanctionRepositoryContract::class => EloquentSanctionRepository::class,
    ];

    public function register(): void
    {
        $this->registerNotificationSender();
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
