<?php

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Domains\Notifications\Http\Resources\NotificationLogResource;
use App\Domains\Notifications\Services\GuardianNotifier;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Journal des notifications envoyees aux tuteurs (cahier des charges 3.3). */
class NotificationLogController extends Controller
{
    public function __construct(private readonly GuardianNotifier $notifier) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->validateFilters($request);

        return NotificationLogResource::collection(
            $this->filtered($request)
                ->with(['student:id,first_name,last_name', 'admissionApplication:id,first_name,last_name,reference'])
                ->orderByDesc('created_at')
                ->paginate($request->integer('per_page', 15))
                ->withQueryString(),
        );
    }

    /**
     * Nombre de messages par statut, sur les memes filtres que la liste sauf le
     * statut : les cartes de synthese restent lisibles quand on filtre sur "echec".
     */
    public function summary(Request $request): JsonResponse
    {
        $this->validateFilters($request);

        $counts = $this->filtered($request, ignoreStatus: true)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byStatus = [];
        foreach (NotificationStatus::cases() as $status) {
            $byStatus[$status->value] = (int) $counts->get($status->value, 0);
        }

        return response()->json(['data' => ['total' => array_sum($byStatus), 'by_status' => $byStatus]]);
    }

    /** Renvoie un message en echec. Le resultat (envoye, ou de nouveau en echec) est dans la reponse. */
    public function resend(NotificationLog $notificationLog): NotificationLogResource
    {
        $log = $this->notifier->resend($notificationLog);

        return new NotificationLogResource($log->load(['student:id,first_name,last_name', 'admissionApplication:id,first_name,last_name,reference']));
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'status' => ['nullable', Rule::in(array_column(NotificationStatus::cases(), 'value'))],
            'type' => ['nullable', Rule::in(array_column(NotificationType::cases(), 'value'))],
            'channel' => ['nullable', Rule::in(array_column(NotificationChannel::cases(), 'value'))],
        ]);
    }

    /** @return Builder<NotificationLog> */
    private function filtered(Request $request, bool $ignoreStatus = false): Builder
    {
        return NotificationLog::query()
            ->when($request->string('student_id')->toString(), fn ($query, $id) => $query->where('student_id', $id))
            ->when($request->string('type')->toString(), fn ($query, $type) => $query->where('type', $type))
            ->when($request->string('channel')->toString(), fn ($query, $channel) => $query->where('channel', $channel))
            ->when(
                ! $ignoreStatus ? $request->string('status')->toString() : null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(
                fn ($match) => $match
                    ->where('recipient', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($student) => $student
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%"))
                    ->orWhereHas('admissionApplication', fn ($application) => $application
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")),
            ));
    }
}
