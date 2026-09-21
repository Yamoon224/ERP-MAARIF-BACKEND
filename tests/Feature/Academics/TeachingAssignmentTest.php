<?php

namespace Tests\Feature\Academics;

use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsSchoolYear;
use Tests\TestCase;

class TeachingAssignmentTest extends TestCase
{
    use BuildsSchoolYear, RefreshDatabase;

    #[Test]
    public function un_administrateur_affecte_un_enseignant_a_une_matiere_d_une_classe(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        ['class' => $class] = $this->schoolYear();
        $subject = Subject::factory()->create(['name' => 'Maths']);

        $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$subject->id}", ['teacher_id' => $teacher->id])
            ->assertOk()
            ->assertJsonPath('data.subject.name', 'Maths')
            ->assertJsonPath('data.teacher.id', $teacher->id);

        $this->actingAs($admin)->getJson("/api/classes/{$class->id}/subjects")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher.name', $teacher->name);
    }

    #[Test]
    public function une_matiere_n_a_qu_un_enseignant_par_classe_le_nouveau_remplace_l_ancien(): void
    {
        $admin = $this->userWithRole('admin');
        $first = $this->userWithRole('teacher');
        $second = $this->userWithRole('teacher');
        ['class' => $class] = $this->schoolYear();
        $subject = Subject::factory()->create();

        $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$subject->id}", ['teacher_id' => $first->id])->assertOk();
        $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$subject->id}", ['teacher_id' => $second->id])->assertOk();

        $this->assertDatabaseCount('class_subject_teacher', 1);
        $this->assertDatabaseHas('class_subject_teacher', ['subject_id' => $subject->id, 'teacher_id' => $second->id]);
    }

    #[Test]
    public function un_enseignant_peut_avoir_plusieurs_matieres_dans_plusieurs_classes(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        ['class' => $sixth] = $this->schoolYear();
        $fifth = SchoolClass::factory()->create(['academic_year' => '2025-2026']);
        $maths = Subject::factory()->create(['name' => 'Maths']);
        $physics = Subject::factory()->create(['name' => 'Physique']);

        foreach ([$sixth, $fifth] as $class) {
            foreach ([$maths, $physics] as $subject) {
                $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$subject->id}", ['teacher_id' => $teacher->id])->assertOk();
            }
        }

        $this->actingAs($teacher)->getJson('/api/me/assignments')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.school_class.academic_year', '2025-2026');
    }

    #[Test]
    public function un_enseignant_ne_voit_que_ses_propres_affectations(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $colleague = $this->userWithRole('teacher');
        ['class' => $class] = $this->schoolYear();
        $mine = Subject::factory()->create();
        $theirs = Subject::factory()->create();

        $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$mine->id}", ['teacher_id' => $teacher->id]);
        $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$theirs->id}", ['teacher_id' => $colleague->id]);

        $this->actingAs($teacher)->getJson('/api/me/assignments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject.id', $mine->id);
    }

    #[Test]
    public function seul_un_compte_enseignant_peut_etre_affecte(): void
    {
        $admin = $this->userWithRole('admin');
        $accountant = $this->userWithRole('accountant');
        ['class' => $class] = $this->schoolYear();
        $subject = Subject::factory()->create();

        $this->actingAs($admin)->putJson("/api/classes/{$class->id}/subjects/{$subject->id}", ['teacher_id' => $accountant->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('teacher_id');

        $this->assertDatabaseCount('class_subject_teacher', 0);
    }

    #[Test]
    public function le_titulaire_d_une_classe_doit_etre_un_enseignant(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        $accountant = $this->userWithRole('accountant');
        ['class' => $class] = $this->schoolYear();

        $this->actingAs($admin)->putJson("/api/classes/{$class->id}", ['main_teacher_id' => $accountant->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('main_teacher_id');
        $this->actingAs($admin)->putJson("/api/classes/{$class->id}", ['main_teacher_id' => $teacher->id])
            ->assertOk()
            ->assertJsonPath('data.main_teacher.id', $teacher->id);
    }

    #[Test]
    public function un_enseignant_ne_peut_pas_modifier_les_affectations(): void
    {
        $teacher = $this->userWithRole('teacher');
        ['class' => $class] = $this->schoolYear();
        $subject = Subject::factory()->create();

        $this->actingAs($teacher)->putJson("/api/classes/{$class->id}/subjects/{$subject->id}", ['teacher_id' => $teacher->id])->assertForbidden();
        $this->actingAs($teacher)->deleteJson("/api/classes/{$class->id}/subjects/{$subject->id}")->assertForbidden();
        $this->actingAs($teacher)->getJson("/api/classes/{$class->id}/subjects")->assertOk();
    }

    #[Test]
    public function un_administrateur_retire_une_affectation(): void
    {
        $admin = $this->userWithRole('admin');
        $teacher = $this->userWithRole('teacher');
        ['class' => $class] = $this->schoolYear();
        $subject = Subject::factory()->create();
        $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]);

        $this->actingAs($admin)->deleteJson("/api/classes/{$class->id}/subjects/{$subject->id}")->assertNoContent();

        $this->assertDatabaseCount('class_subject_teacher', 0);
    }
}
