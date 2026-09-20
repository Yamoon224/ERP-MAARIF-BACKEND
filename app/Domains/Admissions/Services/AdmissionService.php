<?php

namespace App\Domains\Admissions\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Admissions\Contracts\AdmissionRepositoryContract;
use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Domains\Admissions\Exceptions\AdmissionException;
use App\Domains\Admissions\Support\AdmissionReferenceGenerator;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Services\GuardianNotifier;
use App\Domains\Students\Services\StudentService;
use App\Models\AdmissionApplication;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Instruction des candidatures : depot, etude, decision, puis inscription.
 *
 * Un dossier inscrit est fige : l'eleve cree en est devenu la source de
 * verite, modifier ou supprimer le dossier ne changerait plus rien qu'a la
 * trace de son origine.
 */
final class AdmissionService
{
    public function __construct(
        private readonly AdmissionRepositoryContract $applications,
        private readonly SchoolClassRepositoryContract $classes,
        private readonly StudentService $students,
        private readonly GuardianNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AdmissionApplication>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->applications->paginate($filters, $perPage);
    }

    public function find(string $id): AdmissionApplication
    {
        return $this->applications->findOrFail($id);
    }

    /**
     * Compteurs par statut, tous statuts presents meme a zero : l'ecran affiche
     * une carte par statut sans avoir a deviner les manquants.
     *
     * @return array{total: int, by_status: array<string, int>}
     */
    public function summary(?string $academicYear): array
    {
        $counts = $this->applications->countByStatus($academicYear);

        $byStatus = [];
        foreach (AdmissionStatus::cases() as $status) {
            $byStatus[$status->value] = $counts->get($status->value, 0);
        }

        return ['total' => array_sum($byStatus), 'by_status' => $byStatus];
    }

    /** @param  array<string, mixed>  $data */
    public function submit(array $data): AdmissionApplication
    {
        $application = $this->applications->create([
            ...$data,
            'reference' => AdmissionReferenceGenerator::generate(),
            'status' => AdmissionStatus::Pending,
            'submitted_on' => $data['submitted_on'] ?? now()->toDateString(),
        ]);

        return $this->applications->findOrFail($application->id);
    }

    /** @param  array<string, mixed>  $data */
    public function update(AdmissionApplication $application, array $data): AdmissionApplication
    {
        $this->assertNotEnrolled($application);

        return $this->applications->update($application, $data);
    }

    public function changeStatus(AdmissionApplication $application, AdmissionStatus $status, ?string $note, string $userId): AdmissionApplication
    {
        $this->assertNotEnrolled($application);

        $changed = $application->status !== $status;

        $updated = $this->applications->update($application, [
            'status' => $status,
            'decision_note' => $note,
            'decided_at' => now(),
            'decided_by' => $userId,
        ]);

        // Le tuteur n'est prevenu que d'un vrai changement : corriger la note
        // d'une decision deja communiquee ne doit pas lui renvoyer le meme message.
        return $changed ? $this->notifyGuardian($updated) : $updated;
    }

    /**
     * Transforme une candidature admise en eleve inscrit dans la classe
     * choisie. Le mot de passe initial du portail n'est lisible qu'ici, a
     * remettre au tuteur (voir StudentService::enroll).
     *
     * @return array{application: AdmissionApplication, student: Student, initial_password: string}
     */
    public function enroll(AdmissionApplication $application, string $schoolClassId): array
    {
        $this->assertNotEnrolled($application);

        if ($application->status !== AdmissionStatus::Accepted) {
            throw AdmissionException::notAccepted();
        }

        $class = $this->classes->findOrFail($schoolClassId);

        if ($class->academic_year !== $application->academic_year) {
            throw AdmissionException::classYearMismatch($application->academic_year, $class->academic_year);
        }

        $result = DB::transaction(function () use ($application, $class): array {
            $enrolled = $this->students->enroll([
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'gender' => $application->gender,
                'birth_date' => $application->birth_date?->toDateString(),
                'school_class_id' => $class->id,
                'guardian_name' => $application->guardian_name,
                'guardian_phone' => $application->guardian_phone,
                'guardian_email' => $application->guardian_email,
                'address' => $application->address,
            ]);

            $updated = $this->applications->update($application, [
                'status' => AdmissionStatus::Enrolled,
                'student_id' => $enrolled['student']->id,
                'enrolled_at' => now(),
            ]);

            return [
                'application' => $updated,
                'student' => $enrolled['student'],
                'initial_password' => $enrolled['initial_password'],
            ];
        });

        // Apres la transaction : un envoi ne doit jamais retenir ni defaire l'inscription.
        $result['application'] = $this->notifyGuardian($result['application']);

        return $result;
    }

    public function delete(AdmissionApplication $application): void
    {
        $this->assertNotEnrolled($application);

        $this->applications->delete($application);
    }

    /**
     * Previent le tuteur de la decision courante et note sur le dossier que
     * c'est fait, si le prestataire a bien accepte le message.
     */
    private function notifyGuardian(AdmissionApplication $application): AdmissionApplication
    {
        $log = $this->notifier->notifyAdmission($application);

        if ($log?->status !== NotificationStatus::Sent) {
            return $application;
        }

        return $this->applications->update($application, ['notified_at' => now()]);
    }

    private function assertNotEnrolled(AdmissionApplication $application): void
    {
        if ($application->status === AdmissionStatus::Enrolled) {
            throw AdmissionException::alreadyEnrolled();
        }
    }
}
