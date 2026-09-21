<?php

namespace App\Models;

use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Poste de depense de l'etablissement (fournitures, registres, entretien...).
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property-read int|null $expenses_count
 */
class ExpenseCategory extends Model
{
    /** @use HasFactory<ExpenseCategoryFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['name', 'description', 'is_active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
