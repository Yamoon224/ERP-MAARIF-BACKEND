<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Postes de depense de depart d'un etablissement. Idempotent : relancer le
 * seeder ne duplique rien et ne reactive pas une categorie desactivee.
 */
class ExpenseCategorySeeder extends Seeder
{
    /** @var array<string, string> nom => description */
    public const DEFAULTS = [
        'Fournitures scolaires' => 'Craies, marqueurs, cahiers, stylos, papier.',
        'Registres et imprimés' => "Registres d'appel, carnets, bulletins, formulaires et photocopies.",
        'Matériel pédagogique' => 'Livres, cartes, matériel de laboratoire et de sport.',
        'Entretien et réparations' => 'Produits ménagers, petites réparations, peinture.',
        'Eau, électricité et communications' => "Factures d'eau et d'électricité, internet, téléphone.",
        'Mobilier et équipement' => 'Bancs, tables, tableaux, ordinateurs.',
        'Autres dépenses' => 'Toute dépense qui ne rentre pas dans un autre poste.',
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $name => $description) {
            ExpenseCategory::firstOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
