<?php

namespace App\Models;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use Database\Factories\NotificationLogFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'un envoi (ou d'une tentative d'envoi) au tuteur d'un eleve.
 *
 * @property string $id
 * @property NotificationChannel $channel
 * @property NotificationType $type
 * @property NotificationStatus $status
 */
class NotificationLog extends Model
{
    /** @use HasFactory<NotificationLogFactory> */
    use HasFactory, HasUuids;

    protected $table = 'notification_logs';

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'admission_application_id', 'channel', 'type', 'recipient', 'subject', 'body', 'status', 'attempts', 'error', 'sent_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<AdmissionApplication, $this> */
    public function admissionApplication(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class);
    }
}
