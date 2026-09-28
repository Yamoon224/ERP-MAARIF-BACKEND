<?php

namespace Tests\Feature\Students;

use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Import en masse des eleves depuis un tableur (CSV/Excel), pour migrer les donnees d'un systeme existant. */
class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_administrateur_importe_des_eleves_valides_et_recoit_leurs_mots_de_passe(): void
    {
        $admin = $this->userWithRole('admin');
        $schoolClass = SchoolClass::factory()->create();

        $csv = $this->csv([
            ['prenom', 'nom', 'sexe', 'classe', 'annee_scolaire', 'nom_tuteur', 'telephone_tuteur'],
            ['Fatoumata', 'Camara', 'F', $schoolClass->name, $schoolClass->academic_year, 'Ibrahima Camara', '+224612345678'],
            ['Moussa', 'Diallo', 'M', '', '', 'Aissatou Diallo', '+224622334455'],
        ]);

        $response = $this->actingAs($admin)->post('/api/students/import', ['file' => $csv, 'dry_run' => '0']);

        $response->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.valid', 2)->assertJsonPath('data.invalid', 0);
        $this->assertCount(2, $response->json('data.students'));
        $this->assertDatabaseHas('students', ['first_name' => 'Fatoumata', 'last_name' => 'Camara', 'school_class_id' => $schoolClass->id]);
        $this->assertDatabaseHas('students', ['first_name' => 'Moussa', 'last_name' => 'Diallo', 'school_class_id' => null]);
        $this->assertDatabaseHas('enrollments', ['school_class_id' => $schoolClass->id, 'academic_year' => $schoolClass->academic_year]);
    }

    #[Test]
    public function une_ligne_invalide_est_signalee_sans_bloquer_les_autres(): void
    {
        $admin = $this->userWithRole('admin');

        $csv = $this->csv([
            ['prenom', 'nom', 'sexe', 'nom_tuteur', 'telephone_tuteur'],
            ['', 'Camara', 'F', 'Ibrahima Camara', '+224612345678'],
            ['Moussa', 'Diallo', 'M', 'Aissatou Diallo', '+224622334455'],
        ]);

        $response = $this->actingAs($admin)->post('/api/students/import', ['file' => $csv, 'dry_run' => '0']);

        $response->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.valid', 1)->assertJsonPath('data.invalid', 1);
        $this->assertSame(2, $response->json('data.errors.0.row'));
        $this->assertSame(1, Student::query()->count());
    }

    #[Test]
    public function une_classe_introuvable_est_signalee(): void
    {
        $admin = $this->userWithRole('admin');

        $csv = $this->csv([
            ['prenom', 'nom', 'sexe', 'classe', 'annee_scolaire', 'nom_tuteur', 'telephone_tuteur'],
            ['Fatoumata', 'Camara', 'F', 'Inexistante', '2025-2026', 'Ibrahima Camara', '+224612345678'],
        ]);

        $response = $this->actingAs($admin)->post('/api/students/import', ['file' => $csv, 'dry_run' => '0']);

        $response->assertOk()->assertJsonPath('data.invalid', 1);
        $this->assertStringContainsString('introuvable', $response->json('data.errors.0.messages.0'));
        $this->assertSame(0, Student::query()->count());
    }

    #[Test]
    public function un_essai_a_blanc_ne_persiste_rien(): void
    {
        $admin = $this->userWithRole('admin');

        $csv = $this->csv([
            ['prenom', 'nom', 'sexe', 'nom_tuteur', 'telephone_tuteur'],
            ['Fatoumata', 'Camara', 'F', 'Ibrahima Camara', '+224612345678'],
        ]);

        $response = $this->actingAs($admin)->post('/api/students/import', ['file' => $csv, 'dry_run' => '1']);

        $response->assertOk()->assertJsonPath('data.valid', 1)->assertJsonPath('data.dry_run', true);
        $this->assertCount(0, $response->json('data.students'));
        $this->assertSame(0, Student::query()->count());
    }

    #[Test]
    public function l_import_sans_permission_students_manage_est_refuse(): void
    {
        $teacher = $this->userWithRole('teacher');

        $csv = $this->csv([
            ['prenom', 'nom', 'sexe', 'nom_tuteur', 'telephone_tuteur'],
            ['Fatoumata', 'Camara', 'F', 'Ibrahima Camara', '+224612345678'],
        ]);

        $this->actingAs($teacher)->post('/api/students/import', ['file' => $csv])->assertForbidden();
    }

    /** @param  list<list<string>>  $rows */
    private function csv(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, 'eleves.csv', 'text/csv', null, true);
    }
}
