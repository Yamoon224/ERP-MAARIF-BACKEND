<?php

namespace App\Domains\Attendance\Services;

use App\Domains\Attendance\Contracts\AttendanceGateSettingRepositoryContract;
use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Attendance\Exceptions\AttendanceException;
use App\Models\AttendanceGateSetting;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Pointage geolocalise au portail (cahier des charges — pointage par QR
 * code, option 1) : un QR fixe, imprime et affiche au portail, que le
 * parent ou l'eleve scanne depuis le portail. Le pointage n'est enregistre
 * que si l'appareil declare une position a moins du rayon configure des
 * coordonnees de l'etablissement.
 */
final class GateCheckInService
{
    public function __construct(
        private readonly AttendanceGateSettingRepositoryContract $settings,
        private readonly AttendanceRepositoryContract $records,
    ) {}

    public function settings(): AttendanceGateSetting
    {
        return $this->settings->current();
    }

    /** @param  array<string, mixed>  $data */
    public function updateSettings(array $data): AttendanceGateSetting
    {
        return $this->settings->update($this->settings->current(), $data);
    }

    public function regenerateToken(): AttendanceGateSetting
    {
        return $this->settings->regenerateToken($this->settings->current());
    }

    /**
     * Verifie le jeton et la distance, puis pointe l'eleve present pour
     * aujourd'hui. Ecrase un statut deja saisi manuellement pour le jour, de
     * la meme facon qu'un second appel de classe le ferait (voir
     * AttendanceRepositoryContract::recordForDate()) : arriver au portail
     * est la preuve la plus recente et la plus forte de presence.
     */
    public function checkIn(Student $student, string $token, float $latitude, float $longitude): AttendanceRecord
    {
        $setting = $this->settings->current();

        if (! $setting->is_enabled) {
            throw AttendanceException::gateDisabled();
        }

        if (! hash_equals($setting->gate_token, $token)) {
            throw AttendanceException::invalidGateToken();
        }

        $distance = (int) round($setting->distanceInMetersTo($latitude, $longitude));

        if ($distance > $setting->radius_meters) {
            throw AttendanceException::outsideGateRadius($distance);
        }

        return $this->records->recordForDate($student->id, now()->toDateString(), [
            'status' => 'present',
            'justified' => false,
            'reason' => null,
            'recorded_by' => null,
            'source' => 'self_service',
            'checked_in_at' => now(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance_meters' => $distance,
        ]);
    }

    /** Image PNG du QR a afficher/imprimer au portail, encodant le lien de pointage du portail parent. */
    public function qrImage(): string
    {
        $setting = $this->settings->current();

        return (new Builder(
            writer: new PngWriter,
            data: $this->checkInUrl($setting->gate_token),
            size: 400,
            margin: 16,
        ))->build()->getString();
    }

    /** Affiche PDF a imprimer, avec le QR et les consignes de pointage. */
    public function posterPdf(): string
    {
        $qrDataUri = 'data:image/png;base64,'.base64_encode($this->qrImage());

        return Pdf::loadView('exports.gate-poster', ['qrDataUri' => $qrDataUri])
            ->setPaper('a4')
            ->output();
    }

    private function checkInUrl(string $token): string
    {
        return rtrim(config('app.frontend_url'), '/')."/portal/checkin?token={$token}";
    }
}
