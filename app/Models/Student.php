<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Eleve de l'etablissement.
 *
 * `Student` est authentifiable a part entiere : c'est par le couple
 * matricule/mot de passe de l'eleve que son parent accede au portail (cahier
 * des charges 3.1). Un jeton Sanctum emis pour un `Student` donne donc acces
 * en lecture aux informations de cet eleve uniquement — jamais a un autre
 * dossier, meme dans une fratrie inscrite dans le meme etablissement.
 *
 * @property string $id
 * @property string $matricule
 * @property string $first_name
 * @property string $last_name
 * @property string|null $school_class_id
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property-read SchoolClass|null $schoolClass
 */
class Student extends Authenticatable
{
    /** @use HasFactory<StudentFactory> */
    use HasApiTokens, HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'matricule',
        'password',
        'first_name',
        'last_name',
        'gender',
        'birth_date',
        'school_class_id',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'address',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** @return HasMany<Summon, $this> */
    public function summons(): HasMany
    {
        return $this->hasMany(Summon::class);
    }

    /** @return HasMany<Sanction, $this> */
    public function sanctions(): HasMany
    {
        return $this->hasMany(Sanction::class);
    }

    /** @return HasMany<NotificationLog, $this> */
    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
