<?php

namespace App\Domains\Students\Http\Controllers;

use App\Domains\Students\Services\StudentCardService;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Carte scolaire imprimable de l'eleve (QR code de pointage - cahier des
 * charges, option 2), reservee au personnel qui gere les eleves.
 */
class StudentCardController extends Controller
{
    public function __construct(private readonly StudentCardService $cards) {}

    public function show(Student $student): Response
    {
        return $this->pdfResponse($this->cards->cardPdf($student), $this->cards->filename($student));
    }

    public function forClass(SchoolClass $schoolClass): Response
    {
        return $this->pdfResponse($this->cards->classCardsPdf($schoolClass->id), $this->cards->classFilename($schoolClass));
    }

    /** Regenere le jeton de la carte : l'ancienne carte imprimee cesse aussitot de fonctionner. */
    public function regenerateToken(Student $student): JsonResponse
    {
        $this->cards->regenerateToken($student);

        return response()->json(['data' => ['regenerated' => true]]);
    }

    private function pdfResponse(string $content, string $filename): Response
    {
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
