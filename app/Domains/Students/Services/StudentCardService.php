<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Contracts\StudentRepositoryContract;
use App\Models\SchoolClass;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Str;

/**
 * Carte scolaire imprimable de l'eleve, avec un QR code que le surveillant
 * scanne au portail pour le pointer present (cahier des charges - pointage
 * par QR code, option 2). Le QR encode uniquement le jeton opaque de
 * l'eleve (`qr_token`), jamais son matricule : une carte perdue se
 * neutralise en regenerant ce jeton, sans toucher au compte du portail
 * parent.
 */
final class StudentCardService
{
    /**
     * Format CR80 (carte de credit / carte PVC), en points (1 mm = 2.83464567 pt) :
     * chaque carte occupe une page entiere, a la taille exacte imprimee par une
     * imprimante de badges, sans marge a recadrer.
     */
    private const CARD_WIDTH_PT = 242.65;

    private const CARD_HEIGHT_PT = 153.03;

    public function __construct(private readonly StudentRepositoryContract $students) {}

    public function ensureToken(Student $student): string
    {
        if ($student->qr_token === null) {
            $this->students->update($student, ['qr_token' => (string) Str::uuid()]);
        }

        return $student->qr_token;
    }

    public function regenerateToken(Student $student): string
    {
        $this->students->update($student, ['qr_token' => (string) Str::uuid()]);

        return $student->qr_token;
    }

    public function cardPdf(Student $student): string
    {
        $student->loadMissing('schoolClass:id,name');

        return $this->pdf([$student]);
    }

    /** Planche de cartes pour les eleves actifs de la classe, une par eleve. */
    public function classCardsPdf(string $schoolClassId): string
    {
        $students = Student::query()
            ->enrolledInClass($schoolClassId)
            ->where('is_active', true)
            ->with('schoolClass:id,name')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return $this->pdf($students->all());
    }

    public function filename(Student $student): string
    {
        return Str::slug("carte-{$student->matricule}-{$student->last_name}").'.pdf';
    }

    public function classFilename(SchoolClass $schoolClass): string
    {
        return Str::slug("cartes-{$schoolClass->name}").'.pdf';
    }

    /** @param  list<Student>  $students */
    private function pdf(array $students): string
    {
        $cards = array_map(fn (Student $student) => [
            'student' => $student,
            'qrDataUri' => 'data:image/png;base64,'.base64_encode($this->qrImage($this->ensureToken($student))),
        ], $students);

        return Pdf::loadView('exports.student-card', ['cards' => $cards])
            ->setPaper([0, 0, self::CARD_WIDTH_PT, self::CARD_HEIGHT_PT])
            ->output();
    }

    private function qrImage(string $token): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $token,
            size: 260,
            margin: 8,
        ))->build()->getString();
    }
}
