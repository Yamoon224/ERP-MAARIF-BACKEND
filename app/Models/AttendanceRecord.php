<?php

namespace App\Models;

use App\Domains\Attendance\Enums\AttendanceSource;
use App\Domains\Attendance\Enums\AttendanceStatus;
use Database\Factories\AttendanceRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Presence, absence ou retard d'un eleve pour une date donnee (cahier des
 * charges 3.2). Une ligne par eleve et par jour : voir la contrainte
 * d'unicite sur la migration.
 *
 * @property string $id
 * @property string $student_id
 * @property Carbon $date
 * @property AttendanceStatus $status
 * @property bool $justified
 * @property AttendanceSource $source
 * @property Carbon|null $checked_in_at
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $distance_meters
 */
class AttendanceRecord extends Model
{
    /** @use HasFactory<AttendanceRecordFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'date', 'status', 'justified', 'reason', 'recorded_by',
        'source', 'checked_in_at', 'latitude', 'longitude', 'distance_meters',
    ];

    /**
     * Defaut applique en memoire des la creation : la colonne a bien un
     * defaut cote base, mais Eloquent ne le relit pas apres un `create()`
     * sans `refresh()`, et le cast enum de `source` echoue sur `null`.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['source' => 'manual'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Format explicite : sans lui, Eloquent persiste ce cast "date"
            // avec l'heure a minuit ("2026-09-17 00:00:00"), ce qui desaccorde
            // silencieusement la ligne inseree de la recherche par date faite
            // par recordForDate() lors d'un second pointage le meme jour.
            'date' => 'date:Y-m-d',
            'status' => AttendanceStatus::class,
            'justified' => 'boolean',
            'source' => AttendanceSource::class,
            'checked_in_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'distance_meters' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
