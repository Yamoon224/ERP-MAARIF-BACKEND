<?php

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Reporting\Services\TermOverviewService;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Detail d'un trimestre. Les onglets "notes", "sanctions", "convocations" et
 * "presences" reutilisent les listes existantes filtrees par `term_id` : seuls
 * le resume, les matieres et les eleves-avec-resultats ont besoin d'une route
 * propre.
 */
class TermOverviewController extends Controller
{
    public function __construct(private readonly TermOverviewService $overview) {}

    public function summary(Request $request, Term $term): JsonResponse
    {
        return response()->json([
            'data' => $this->overview->summary($term, $request->user()->can('discipline.manage')),
        ]);
    }

    public function subjects(Term $term): JsonResponse
    {
        return response()->json(['data' => $this->overview->subjects($term)->values()]);
    }

    public function students(Request $request, Term $term): JsonResponse
    {
        $request->validate(['school_class_id' => ['nullable', 'uuid']]);

        $result = $this->overview->students(
            $term,
            $request->only('search', 'school_class_id'),
            $request->integer('per_page', 15),
        );
        $page = $result['page'];

        return response()->json([
            'data' => $page->getCollection()->map(fn (Enrollment $enrollment) => [
                'enrollment_id' => $enrollment->id,
                'student' => [
                    'id' => $enrollment->student->id,
                    'name' => $enrollment->student->fullName(),
                    'matricule' => $enrollment->student->matricule,
                    'is_active' => $enrollment->student->is_active,
                ],
                'school_class' => $enrollment->schoolClass ? [
                    'id' => $enrollment->schoolClass->id,
                    'name' => $enrollment->schoolClass->name,
                ] : null,
                'average' => $result['averages']->get($enrollment->student_id),
                'absences' => $result['absences'][$enrollment->student_id] ?? 0,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
