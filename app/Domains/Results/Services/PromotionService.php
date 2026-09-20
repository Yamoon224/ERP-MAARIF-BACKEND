<?php

namespace App\Domains\Results\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Results\Contracts\PromotionDecisionRepositoryContract;
use App\Domains\Results\Enums\PromotionDecisionType;
use App\Domains\Results\Exceptions\ResultsException;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Domains\Students\Services\EnrollmentService;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;

/**
 * Passage d'une classe a l'annee suivante, d'apres les decisions de fin
 * d'annee : les admis sont reinscrits dans la classe superieure, les
 * redoublants dans la classe qu'ils repetent, les exclus ne sont pas reinscrits.
 *
 * Seules les decisions *enregistrees* comptent, jamais les suggestions : on ne
 * reinscrit pas un eleve sur la foi d'un calcul que personne n'a valide.
 *
 * L'operation est rejouable : un eleve deja inscrit pour l'annee cible est
 * laisse tel quel, ce qui permet de la relancer apres avoir valide les
 * decisions restantes sans deplacer ceux qu'on a places a la main.
 */
final class PromotionService
{
    public function __construct(
        private readonly SchoolClassRepositoryContract $classes,
        private readonly EnrollmentRepositoryContract $enrollments,
        private readonly EnrollmentService $enrolling,
        private readonly PromotionDecisionRepositoryContract $decisions,
    ) {}

    /**
     * @return array{promoted: int, repeated: int, excluded: int, undecided: int, already_enrolled: int, without_class: int, inactive: int}
     */
    public function promoteClass(SchoolClass $source, string $admittedClassId, ?string $repeatClassId = null): array
    {
        $admittedClass = $this->classes->findOrFail($admittedClassId);
        $repeatClass = $repeatClassId === null ? null : $this->classes->findOrFail($repeatClassId);

        $this->assertTarget($source, $admittedClass, $repeatClass);

        $enrollments = $this->enrollments->forClass($source->id)->load('student');
        $decisions = $this->decisions->forEnrollments($enrollments->pluck('id')->all());

        $summary = ['promoted' => 0, 'repeated' => 0, 'excluded' => 0, 'undecided' => 0, 'already_enrolled' => 0, 'without_class' => 0, 'inactive' => 0];

        DB::transaction(function () use ($enrollments, $decisions, $admittedClass, $repeatClass, &$summary): void {
            /** @var Enrollment $enrollment */
            foreach ($enrollments as $enrollment) {
                $decision = $decisions->get($enrollment->id)?->decision;

                if ($decision === null) {
                    $summary['undecided']++;

                    continue;
                }

                if ($decision === PromotionDecisionType::Excluded) {
                    $summary['excluded']++;

                    continue;
                }

                if (! $enrollment->student->is_active) {
                    $summary['inactive']++;

                    continue;
                }

                $target = $decision === PromotionDecisionType::Admitted ? $admittedClass : $repeatClass;

                if ($target === null) {
                    $summary['without_class']++;

                    continue;
                }

                $alreadyEnrolled = $this->enrollments->forStudent($enrollment->student_id)
                    ->contains('academic_year', $target->academic_year);

                if ($alreadyEnrolled) {
                    $summary['already_enrolled']++;

                    continue;
                }

                $this->enrolling->enroll($enrollment->student, $target->id);
                $summary[$decision === PromotionDecisionType::Admitted ? 'promoted' : 'repeated']++;
            }
        });

        return $summary;
    }

    /** Les classes d'accueil sont d'une meme annee, strictement posterieure a celle de la classe d'origine. */
    private function assertTarget(SchoolClass $source, SchoolClass $admitted, ?SchoolClass $repeat): void
    {
        $sameYear = $repeat === null || $repeat->academic_year === $admitted->academic_year;

        // Les annees `AAAA-AAAA` se comparent correctement comme du texte.
        if ($admitted->academic_year <= $source->academic_year || ! $sameYear) {
            throw ResultsException::invalidPromotionTarget($source->academic_year, $sameYear ? $admitted->academic_year : "{$admitted->academic_year} / {$repeat?->academic_year}");
        }
    }
}
