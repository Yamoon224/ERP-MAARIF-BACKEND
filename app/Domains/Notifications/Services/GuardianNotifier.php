<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\Sanction;
use App\Models\Student;
use App\Models\Summon;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Alerte le tuteur d'un eleve en cas de convocation ou de sanction (cahier
 * des charges 3.3).
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

    private function dispatch(Student $student, NotificationType $type, string $body, string $subject): NotificationLog
    {
        $channel = $student->guardian_email !== null ? NotificationChannel::Email : NotificationChannel::Sms;
        $recipient = $channel === NotificationChannel::Email ? $student->guardian_email : $student->guardian_phone;

        $log = NotificationLog::create([
            'student_id' => $student->id,
            'channel' => $channel,
            'type' => $type,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => NotificationStatus::Pending,
        ]);

        try {
            $accepted = $this->sender->send(new NotificationMessage(
                channel: $channel,
                recipient: $recipient,
                body: $body,
                subject: $subject,
            ));

            $log->update([
                'status' => $accepted ? NotificationStatus::Sent : NotificationStatus::Failed,
                'sent_at' => $accepted ? Carbon::now() : null,
                'error' => $accepted ? null : 'Message refuse par le prestataire.',
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => NotificationStatus::Failed,
                'error' => mb_substr($exception->getMessage(), 0, 250),
            ]);
        }

        return $log->refresh();
    }
}
