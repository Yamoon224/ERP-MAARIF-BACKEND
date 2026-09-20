<?php

namespace App\Domains\Notifications\Senders;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Pilote par defaut : envoie reellement les e-mails via le mailer configure
 * (`MAIL_MAILER`, `array` ou `log` en developpement), et journalise les SMS —
 * aucune passerelle SMS n'est cablee dans ce depot de reference, mais le
 * point d'extension est unique (voir App\Providers\DomainServiceProvider).
 *
 * Le numero de telephone est tronque avant journalisation : un journal
 * applicatif est copie et exporte plus largement qu'une base de donnees, et
 * n'a pas vocation a devenir un fichier nominatif de coordonnees de tuteurs.
 */
final class LogNotificationSender implements NotificationSenderContract
{
    public function send(NotificationMessage $message): bool
    {
        if ($message->channel === NotificationChannel::Email) {
            Mail::raw($message->body, function ($mail) use ($message): void {
                $mail->to($message->recipient)->subject($message->subject ?? 'Notification Maarif ERP');
            });

            return true;
        }

        Log::info('SMS sortant (aucune passerelle configuree)', [
            'recipient' => $this->mask($message->recipient),
            'body' => $message->body,
        ]);

        return true;
    }

    private function mask(string $recipient): string
    {
        $length = strlen($recipient);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return substr($recipient, 0, 4).str_repeat('*', $length - 6).substr($recipient, -2);
    }
}
