<?php

namespace Tests\Feature\Students;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_administrateur_inscrit_un_eleve_et_recoit_le_mot_de_passe_initial(): void
    {
        $admin = $this->userWithRole('admin');
        $schoolClass = SchoolClass::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/students', [
            'first_name' => 'Fatoumata',
            'last_name' => 'Camara',
            'gender' => 'F',
            'birth_date' => '2014-05-10',
            'school_class_id' => $schoolClass->id,
            'guardian_name' => 'Ibrahima Camara',
            'guardian_phone' => '+224612345678',
            'guardian_email' => 'ibrahima@example.test',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'matricule', 'initial_password']]);

        $this->assertMatchesRegularExpression('/^MAA-\d{4}-\d{6}$/', $response->json('data.matricule'));
        $this->assertDatabaseHas('students', ['first_name' => 'Fatoumata', 'last_name' => 'Camara']);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_inscrire_un_eleve(): void
    {
        $teacher = $this->userWithRole('teacher');

        $this->actingAs($teacher)->postJson('/api/students', [
            'first_name' => 'Test',
            'last_name' => 'Eleve',
            'gender' => 'M',
            'guardian_name' => 'Parent',
            'guardian_phone' => '+224600000000',
        ])->assertStatus(403);
    }

    #[Test]
    public function un_enseignant_peut_consulter_la_liste_des_eleves(): void
    {
        $teacher = $this->userWithRole('teacher');
        Student::factory()->count(3)->create();

        $this->actingAs($teacher)->getJson('/api/students')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function la_reinitialisation_du_mot_de_passe_change_le_mot_de_passe_du_portail(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->studentWithPassword();

        $response = $this->actingAs($admin)->postJson("/api/students/{$student->id}/reset-password");

        $response->assertOk()->assertJsonStructure(['data' => ['initial_password']]);

        $newPassword = $response->json('data.initial_password');

        $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => 'password'])
            ->assertStatus(422);

        $this->postJson('/api/parent/login', ['matricule' => $student->matricule, 'password' => $newPassword])
            ->assertOk();
    }
}
