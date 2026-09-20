<?php

namespace App\Models;

use Database\Factories\TermFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trimestre scolaire. `is_current` designe le trimestre propose par defaut
 * aux ecrans de saisie (voir App\Domains\Academics\Services\TermService, qui
 * garantit qu'un seul trimestre est courant a la fois).
 *
 * @property string $id
 * @property string $name
 * @property string $academic_year
 * @property bool $is_current
 */
class Term extends Model
{
    /** @use HasFactory<TermFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'academic_year', 'starts_at', 'ends_at', 'is_current'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date:Y-m-d',
            'ends_at' => 'date:Y-m-d',
            'is_current' => 'boolean',
        ];
    }

    /** @return HasMany<Grade, $this> */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
