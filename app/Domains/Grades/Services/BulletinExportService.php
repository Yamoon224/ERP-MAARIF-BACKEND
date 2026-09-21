<?php

namespace App\Domains\Grades\Services;

use App\Domains\Results\Support\Mention;
use App\Models\Student;
use App\Models\Term;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export du bulletin d'un eleve en PDF (a remettre ou imprimer) ou en XLSX
 * (a retravailler dans un tableur).
 *
 * Les deux formats partent du meme document, construit une seule fois : un
 * bulletin ne doit jamais afficher une moyenne differente selon le format
 * telecharge.
 *
 * @phpstan-type BulletinDocument array{
 *     student: string,
 *     matricule: string,
 *     class: string|null,
 *     term: string,
 *     academic_year: string,
 *     subjects: list<array{subject: string, code: string, coefficient: float, average: float, grades_count: int}>,
 *     overall_average: float|null,
 *     mention: string|null,
 *     issued_on: string
 * }
 */
final class BulletinExportService
{
    public const FORMATS = ['pdf', 'xlsx'];

    public function __construct(private readonly BulletinService $bulletins) {}

    /** @return BulletinDocument */
    public function document(Student $student, string $termId): array
    {
        $term = Term::query()->findOrFail($termId);
        $bulletin = $this->bulletins->build($student->id, $termId);

        // La classe de l'annee du trimestre, pas la classe actuelle : le
        // bulletin d'une annee passee doit rester celui de cette annee-la.
        $class = $student->enrollments()
            ->where('academic_year', $term->academic_year)
            ->with('schoolClass:id,name')
            ->first()?->schoolClass
            ?? $student->schoolClass;

        return [
            'student' => $student->fullName(),
            'matricule' => $student->matricule,
            'class' => $class?->name,
            'term' => $term->name,
            'academic_year' => $term->academic_year,
            'subjects' => $bulletin['subjects'],
            'overall_average' => $bulletin['overall_average'],
            'mention' => Mention::forAverage($bulletin['overall_average']),
            'issued_on' => now()->format('d/m/Y'),
        ];
    }

    /** @param  BulletinDocument  $bulletin */
    public function pdf(array $bulletin): string
    {
        return Pdf::loadView('exports.bulletin', ['bulletin' => $bulletin])
            ->setPaper('a4')
            ->output();
    }

    /** @param  BulletinDocument  $bulletin */
    public function xlsx(array $bulletin): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet()->setTitle('Bulletin');

        $sheet->setCellValue('A1', 'Bulletin de notes');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $identity = [
            ['Élève', $bulletin['student']],
            ['Matricule', $bulletin['matricule']],
            ['Classe', $bulletin['class'] ?? '—'],
            ['Période', "{$bulletin['term']} — {$bulletin['academic_year']}"],
        ];
        foreach ($identity as $offset => [$label, $value]) {
            $row = 3 + $offset;
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $this->setText($sheet, "B{$row}", $value);
        }

        $headerRow = 8;
        foreach (['Matière', 'Code', 'Coefficient', 'Nombre de notes', 'Moyenne /20'] as $index => $title) {
            $sheet->setCellValue([$index + 1, $headerRow], $title);
        }
        $sheet->getStyle("A{$headerRow}:E{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $row = $headerRow + 1;
        foreach ($bulletin['subjects'] as $subject) {
            $this->setText($sheet, "A{$row}", $subject['subject']);
            $this->setText($sheet, "B{$row}", $subject['code']);
            $sheet->setCellValue("C{$row}", $subject['coefficient']);
            $sheet->setCellValue("D{$row}", $subject['grades_count']);
            $sheet->setCellValue("E{$row}", $subject['average']);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Moyenne générale');
        $sheet->setCellValue("E{$row}", $bulletin['overall_average'] ?? '—');
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF2FF');

        if ($bulletin['mention'] !== null) {
            $sheet->setCellValue('A'.($row + 1), 'Appréciation');
            $sheet->setCellValue('E'.($row + 1), $bulletin['mention']);
        }

        $sheet->getStyle("C{$headerRow}:E".($row + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 30, 'B' => 14, 'C' => 14, 'D' => 18, 'E' => 16] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param  BulletinDocument  $bulletin */
    public function filename(array $bulletin, string $format): string
    {
        return Str::slug("bulletin-{$bulletin['matricule']}-{$bulletin['term']}").".{$format}";
    }

    /** Texte saisi par des humains : jamais interprete comme une formule (=, +, @...) par le tableur. */
    private function setText(Worksheet $sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }
}
