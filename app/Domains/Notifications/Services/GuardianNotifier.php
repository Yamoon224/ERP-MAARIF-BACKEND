<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Domains\Notifications\Exceptions\NotificationException;
use App\Models\AdmissionApplication;
use App\Models\NotificationLog;
use App\Models\Sanction;
use App\Models\Student;
use App\Models\Summon;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Alerte le tuteur d'un eleve en cas de convocation ou de sanction (cahier
 * des charges 3.3), et celui d'un candidat quand son dossier d'admission est
 * decide.
 *
 * L'envoi ne peut jamais faire echouer la creation de la convocation ou de la
 * sanction qui le declenche : il est appele apres l'ecriture en base, et
 * toute exception y est capturee. Un operateur SMS ou un serveur mail
 * indisponible ne doit pas transformer une sanction bel et bien enregistree
 * en erreur 500 pour l'administrateur qui vient de la saisir.
 *
 * Chaque tentative est tracee, y compris ses echecs : "je n'ai pas recu la
 * convocation" est la premiere reclamation attendue d'un parent, et sans
 * journal la reponse serait une conjecture.
 */
final class GuardianNotifier
{
    public function __construct(private readonly NotificationSenderContract $sender) {}

    public function notifySummon(Summon $summon): NotificationLog
    {
        $summon->loadMissing('student');
        $student = $summon->student;

        $body = sprintf(
            'Convocation pour %s le %s. Motif : %s.',
            $student->fullName(),
            $summon->scheduled_at->format('d/m/Y a H:i'),
            $summon->reason,
        );

        return $this->dispatch($student, NotificationType::Summon, $body, 'Convocation - '.$student->fullName());
    }

    public function notifySanction(Sanction $sanction): NotificationLog
    {
        $sanction->loadMissing('student');
        $student = $sanction->student;

        $body = sprintf(
            'Sanction disciplinaire (%s) pour %s. Motif : %s.',
            $sanction->type->label(),
            $student->fullName(),
            $sanction->reason,
        );

        return $this->dispatch($student, NotificationType::Sanction, $body, 'Sanction disciplinaire - '.$student->fullName());
    }

    /**
     * Informe le tuteur d'un candidat d'une decision sur son dossier : admis,
     * liste d'attente, refus, puis inscription. La mise en etude est une etape
     * interne : elle ne declenche rien (retour `null`).
     *
     * Les coordonnees sont celles du dossier, l'eleve n'existant pas encore.
     * Le mot de passe du portail n'est jamais envoye par message : il est
     * remis au tuteur par l'etablissement.
     */
    public function notifyAdmission(AdmissionApplication $application): ?NotificationLog
    {
        $application->loadMissing('student');

        $who = sprintf('%s (%s)', $application->fullName(), $application->reference);
        $target = sprintf('%s, annee %s', $application->level, $application->academic_year);

        $body = match ($application->status) {
            AdmissionStatus::Accepted => "Admission de {$who} : candidature admise pour {$target}. L'etablissement vous contactera pour finaliser l'inscription.",
            AdmissionStatus::Waitlisted => "Admission de {$who} : candidature placee en liste d'attente pour {$target}. Vous serez prevenu si une place se libere.",
            AdmissionStatus::Rejected => "Admission de {$who} : candidature non retenue pour {$target}. Motif : {$application->decision_note}.",
            AdmissionStatus::Enrolled => "Admission de {$who} : inscription confirmee pour {$target}. Matricule : {$application->student?->matricule}. Le mot de passe du portail vous est remis par l'etablissement.",
            default => null,
        };

        if ($body === null) {
            return null;
        }

        return $this->deliver(
            ['admission_application_id' => $application->id],
            $application->guardian_email,
            $application->guardian_phone,
            NotificationType::Admission,
            $body,
            'Admission - '.$application->fullName(),
        );
    }

    private function dispatch(Student $student, NotificationType $type, string $body, string $subject): NotificationLog
    {
        return $this->deliver(['student_id' => $student->id], $student->guardian_email, $student->guardian_phone, $type, $body, $subject);
    }

    /**
     * @param  array<string, string>  $owner  `student_id` ou `admission_application_id`
     */
    private function deliver(array $owner, ?string $email, string $phone, NotificationType $type, string $body, string $subject): NotificationLog
    {
        $channel = $email !== null ? NotificationChannel::Email : NotificationChannel::Sms;
        $recipient = $channel === NotificationChannel::Email ? $email : $phone;

        $log = NotificationLog::create([
            ...$owner,
            'channel' => $channel,
            'type' => $type,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => NotificationStatus::Pending,
        ]);

        return $this->attempt($log);
    }

    /**
     * Renvoie un message en echec, sur la meme ligne du journal (le compteur de
     * tentatives augmente). Le contact est relu sur l'eleve ou le dossier
     * plutot que sur le message : c'est le cas courant d'un numero corrige
     * apres l'echec, qu'un renvoi a l'ancienne adresse ferait echouer encore.
     */
    public function resend(NotificationLog $log): NotificationLog
    {
        if ($log->status !== NotificationStatus::Failed) {
            throw NotificationException::notResendable();
        }

        $log->loadMissing(['student', 'admissionApplication']);

        $owner = $log->student ?? $log->admissionApplication;
        if ($owner !== null) {
            $channel = $owner->guardian_email !== null ? NotificationChannel::Email : NotificationChannel::Sms;

            $log->fill([
                'channel' => $channel,
                'recipient' => $channel === NotificationChannel::Email ? $owner->guardian_email : $owner->guardian_phone,
            ]);
        }

        $log->attempts++;

        $log = $this->attempt($log);

        // Une decision d'admission enfin delivree : le dossier peut le dire.
        if ($log->status === NotificationStatus::Sent && $log->admissionApplication?->notified_at === null) {
            $log->admissionApplication?->update(['notified_at' => Carbon::now()]);
        }

        return $log;
    }

    /** Envoie le message du journal et consigne le resultat, sans jamais laisser une erreur d'envoi remonter. */
    private function attempt(NotificationLog $log): NotificationLog
    {
        try {
            $accepted = $this->sender->send(new NotificationMessage(
                channel: $log->channel,
                recipient: $log->recipient,
                body: $log->body,
                subject: $log->subject,
            ));

            $log->update([
                'status' => $accepted ? NotificationStatus::Sent : NotificationStatus::Failed,
                'sent_at' => $accepted ? Carbon::now() : null,
                'error' => $accepted ? null : 'Message refuse par le prestataire.',
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => NotificationStatus::Failed,
                'sent_at' => null,
                'error' => mb_substr($exception->getMessage(), 0, 250),
            ]);
        }

        return $log->refresh();
    }
}
