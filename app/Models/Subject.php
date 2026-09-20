<?php

namespace App\Models;

use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Matiere enseignee, avec son coefficient dans le calcul de la moyenne
 * generale (voir App\Domains\Grades\Services\BulletinService).
 *
 * @property string $id
 * @property string $name
 * @property string $code
 * @property float $coefficient
 */
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'code', 'coefficient'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
        ];
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
