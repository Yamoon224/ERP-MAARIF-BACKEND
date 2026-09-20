<?php

namespace App\Domains\Grades\Services;

use App\Domains\Grades\Contracts\GradeRepositoryContract;

/**
 * Calcul du bulletin d'un eleve pour un trimestre (cahier des charges 3.2).
 *
 * Chaque note est d'abord ramenee sur 20 (voir Grade::normalizedOn20), puis
 * moyennee par matiere, puis ponderee par le coefficient de la matiere pour
 * la moyenne generale. Une matiere sans aucune note ce trimestre est absente
 * du bulletin plutot que comptee pour zero : un devoir non encore rendu ne
 * doit pas faire chuter une moyenne generale avant meme d'avoir eu lieu.
 */
final class BulletinService
{
    public function __construct(private readonly GradeRepositoryContract $grades) {}

    /** @return array{subjects: list<array{subject: string, code: string, coefficient: float, average: float, grades_count: int}>, overall_average: float|null} */
    public function build(string $studentId, string $termId): array
    {
        $grades = $this->grades->forStudentAndTerm($studentId, $termId);

        $bySubject = $grades->groupBy('subject_id');

        $subjects = [];
        $weightedSum = 0.0;
        $totalCoefficient = 0.0;

        foreach ($bySubject as $subjectGrades) {
            $subject = $subjectGrades->first()->subject;
            $average = round($subjectGrades->avg(fn ($grade) => $grade->normalizedOn20()), 2);
            $coefficient = (float) $subject->coefficient;

            $subjects[] = [
                'subject' => $subject->name,
                'code' => $subject->code,
                'coefficient' => $coefficient,
                'average' => $average,
                'grades_count' => $subjectGrades->count(),
            ];

            $weightedSum += $average * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return [
            'subjects' => $subjects,
            'overall_average' => $totalCoefficient > 0 ? round($weightedSum / $totalCoefficient, 2) : null,
        ];
    }
}
