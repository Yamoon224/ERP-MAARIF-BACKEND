<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Compte du personnel : administrateur ou enseignant.
 *
 * Les parents ne sont pas des `User` : ils s'authentifient avec le matricule
 * et le mot de passe de leur enfant (voir App\Models\Student et
 * App\Domains\Auth\Services\ParentAuthService). Melanger les deux dans une
 * seule table aurait force chaque colonne "personnel" (roles, permissions) a
 * etre nullable pour un parent, et inversement pour les colonnes eleve.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable;

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Classes dont cet enseignant est le titulaire.
     *
     * @return HasMany<SchoolClass, $this>
     */
    public function mainClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class, 'main_teacher_id');
    }

    /** Notes saisies par cet enseignant.
     *
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'teacher_id');
    }
}
