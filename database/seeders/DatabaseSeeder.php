<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Point d'entree du peuplement. L'ordre compte : les roles doivent exister
 * avant qu'un compte ne s'y rattache.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ExpenseCategorySeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
