<?php

namespace App\Domains\Notifications\Http\Resources;

use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificationLog */
class NotificationLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student' => $this->whenLoaded('student', fn () => $this->student ? [
                'id' => $this->student->id,
                'name' => $this->student->fullName(),
            ] : null),
            'admission' => $this->whenLoaded('admissionApplication', fn () => $this->admissionApplication ? [
                'id' => $this->admissionApplication->id,
                'name' => $this->admissionApplication->fullName(),
                'reference' => $this->admissionApplication->reference,
            ] : null),
            'channel' => $this->channel->value,
            'type' => $this->type->value,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status->value,
            'error' => $this->error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
