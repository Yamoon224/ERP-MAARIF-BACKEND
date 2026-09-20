<?php

namespace App\Domains\Grades\Http\Controllers;

use App\Domains\Grades\Services\BulletinService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bulletin d'un eleve pour un trimestre (cahier des charges 3.2), consultable
 * par le personnel pour n'importe quel eleve, et par le parent pour son
 * enfant uniquement (voir `mine`, qui ne lit jamais l'identifiant depuis la
 * requete).
 */
class BulletinController extends Controller
{
    public function __construct(private readonly BulletinService $bulletins) {}

    public function forStudent(Request $request, Student $student): JsonResponse
    {
        return $this->respond($student, $this->resolveTermId($request));
    }

    public function mine(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return $this->respond($student, $this->resolveTermId($request));
    }

    private function respond(Student $student, ?string $termId): JsonResponse
    {
        if ($termId === null) {
            return response()->json([
                'message' => 'Aucun trimestre courant n\'est configure.',
                'error_code' => 'no_current_term',
                'context' => (object) [],
            ], 422);
        }

        return response()->json([
            'data' => [
                'student' => ['id' => $student->id, 'name' => $student->fullName(), 'matricule' => $student->matricule],
                'term_id' => $termId,
                ...$this->bulletins->build($student->id, $termId),
            ],
        ]);
    }

    private function resolveTermId(Request $request): ?string
    {
        $termId = $request->query('term_id');

        if (is_string($termId) && $termId !== '') {
            return $termId;
        }

        return Term::query()->where('is_current', true)->value('id');
    }
}
