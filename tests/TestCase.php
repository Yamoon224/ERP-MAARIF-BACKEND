<?php

namespace Tests;

use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Cree un compte du personnel porteur d'un role.
     *
     * Le role vient du seeder versionne et non d'une matrice inventee pour le
     * test : un test qui s'appuierait sur des permissions fabriquees a la
     * main passerait avec une matrice de production differente, ce qui est
     * exactement le contraire de ce qu'on lui demande.
     */
    protected function userWithRole(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->refresh();
    }

    /** Cree un eleve dont le mot de passe en clair est "password" (voir StudentFactory). */
    protected function studentWithPassword(): Student
    {
        return Student::factory()->create();
    }
}
