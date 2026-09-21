<?php

namespace App\Domains\Grades\Http\Controllers;

use App\Domains\Grades\Services\BulletinExportService;
use App\Domains\Grades\Services\BulletinService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Bulletin d'un eleve pour un trimestre (cahier des charges 3.2), consultable
 * par le personnel pour n'importe quel eleve, et par le parent pour son
 * enfant uniquement (voir `mine`, qui ne lit jamais l'identifiant depuis la
 * requete).
 */
class BulletinController extends Controller
{
    public function __construct(
        private readonly BulletinService $bulletins,
        private readonly BulletinExportService $exports,
    ) {}

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

    /** Bulletin d'un eleve au format `pdf` ou `xlsx`, pour le personnel. */
    public function exportForStudent(Request $request, Student $student): Response|JsonResponse
    {
        return $this->download($request, $student);
    }

    /** Bulletin de l'enfant du parent connecte au format `pdf` ou `xlsx`. */
    public function exportMine(Request $request): Response|JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        return $this->download($request, $student);
    }

    private function download(Request $request, Student $student): Response|JsonResponse
    {
        $format = $request->validate([
            'format' => ['required', Rule::in(BulletinExportService::FORMATS)],
        ])['format'];

        $termId = $this->resolveTermId($request);

        if ($termId === null) {
            return $this->noCurrentTerm();
        }

        $bulletin = $this->exports->document($student, $termId);
        $content = $format === 'pdf' ? $this->exports->pdf($bulletin) : $this->exports->xlsx($bulletin);

        return response($content, 200, [
            'Content-Type' => $format === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->exports->filename($bulletin, $format).'"',
        ]);
    }

    private function noCurrentTerm(): JsonResponse
    {
        return response()->json([
            'message' => 'Aucun trimestre courant n\'est configure.',
            'error_code' => 'no_current_term',
            'context' => (object) [],
        ], 422);
    }

    private function respond(Student $student, ?string $termId): JsonResponse
    {
        if ($termId === null) {
            return $this->noCurrentTerm();
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
