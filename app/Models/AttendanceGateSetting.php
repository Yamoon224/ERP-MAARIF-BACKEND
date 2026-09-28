<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Reglage (unique) du pointage geolocalise au portail (cahier des charges -
 * pointage par QR code, option 1). Voir
 * App\Domains\Attendance\Contracts\AttendanceGateSettingRepositoryContract::current(),
 * qui garantit qu'une seule ligne existe.
 *
 * @property string $id
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int $radius_meters
 * @property string $gate_token
 * @property bool $is_enabled
 */
class AttendanceGateSetting extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['latitude', 'longitude', 'radius_meters', 'gate_token', 'is_enabled'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'radius_meters' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * Distance en metres jusqu'a un point donne (formule de Haversine).
     *
     * La geolocalisation HTML5 envoyee par le navigateur du parent est une
     * donnee declarative, pas une preuve cryptographique : ce calcul est un
     * frein raisonnable contre une erreur ou un pointage a distance, pas une
     * garantie absolue de presence physique.
     */
    public function distanceInMetersTo(float $latitude, float $longitude): float
    {
        $earthRadiusMeters = 6_371_000;

        $latFrom = deg2rad((float) $this->latitude);
        $lonFrom = deg2rad((float) $this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(
            sin($latDelta / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($lonDelta / 2) ** 2
        ));

        return $angle * $earthRadiusMeters;
    }
}
