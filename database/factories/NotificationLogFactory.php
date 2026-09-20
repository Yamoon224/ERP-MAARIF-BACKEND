<?php

namespace Database\Factories;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Models\NotificationLog;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationLog> */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'channel' => NotificationChannel::Email,
            'type' => NotificationType::Summon,
            'recipient' => fake()->safeEmail(),
            'subject' => 'Convocation',
            'body' => fake()->sentence(),
            'status' => NotificationStatus::Sent,
            'error' => null,
            'sent_at' => now(),
        ];
    }
}
