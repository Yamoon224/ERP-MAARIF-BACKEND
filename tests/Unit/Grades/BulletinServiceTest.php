<?php

namespace Tests\Unit\Grades;

use App\Domains\Grades\Repositories\EloquentGradeRepository;
use App\Domains\Grades\Services\BulletinService;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BulletinServiceTest extends TestCase
{
    use RefreshDatabase;

    private BulletinService $bulletins;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bulletins = new BulletinService(new EloquentGradeRepository);
    }

    #[Test]
    public function il_pondere_la_moyenne_generale_par_le_coefficient_de_chaque_matiere(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $maths = Subject::factory()->create(['coefficient' => 4]);
        $arts = Subject::factory()->create(['coefficient' => 1]);

        // Maths : moyenne de 10 sur 20, poids 4.
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $maths->id, 'value' => 10, 'max_value' => 20]);
        // Arts : moyenne de 20 sur 20, poids 1.
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $arts->id, 'value' => 20, 'max_value' => 20]);

        $bulletin = $this->bulletins->build($student->id, $term->id);

        // (10*4 + 20*1) / 5 = 12.
        $this->assertSame(12.0, $bulletin['overall_average']);
        $this->assertCount(2, $bulletin['subjects']);
    }

    #[Test]
    public function une_matiere_sans_note_ce_trimestre_est_absente_du_bulletin(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $subjectWithGrade = Subject::factory()->create();
        Subject::factory()->create(); // Aucune note pour celle-ci.

        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subjectWithGrade->id, 'value' => 15, 'max_value' => 20]);

        $bulletin = $this->bulletins->build($student->id, $term->id);

        $this->assertCount(1, $bulletin['subjects']);
    }

    #[Test]
    public function sans_aucune_note_la_moyenne_generale_est_nulle_plutot_que_zero(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();

        $bulletin = $this->bulletins->build($student->id, $term->id);

        $this->assertSame([], $bulletin['subjects']);
        $this->assertNull($bulletin['overall_average']);
    }

    #[Test]
    public function les_notes_sur_un_bareme_different_sont_ramenees_a_20_avant_la_moyenne(): void
    {
        $student = Student::factory()->create();
        $term = Term::factory()->create();
        $subject = Subject::factory()->create(['coefficient' => 1]);

        // 8/10 et 16/20 valent tous deux 16/20 : la moyenne doit etre 16, pas 12.
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 8, 'max_value' => 10]);
        Grade::factory()->create(['student_id' => $student->id, 'term_id' => $term->id, 'subject_id' => $subject->id, 'value' => 16, 'max_value' => 20]);

        $bulletin = $this->bulletins->build($student->id, $term->id);

        $this->assertSame(16.0, $bulletin['subjects'][0]['average']);
    }
}
