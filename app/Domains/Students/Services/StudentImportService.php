<?php

namespace App\Domains\Students\Services;

use App\Domains\Academics\Services\AcademicYearService;
use App\Models\SchoolClass;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Import en masse d'eleves depuis un tableur (CSV ou Excel), pour migrer les
 * donnees d'un systeme existant sans ressaisie manuelle.
 *
 * Chaque ligne est traitee independamment : une ligne invalide est
 * signalee et n'empeche pas les autres d'etre importees. C'est le bon
 * compromis pour une migration ponctuelle de donnees externes forcement
 * imparfaites — contrairement a l'appel de classe (voir
 * AttendanceService::recordClass()), qui est une saisie quotidienne ou
 * "tout ou rien" a du sens.
 */
final class StudentImportService
{
    /** En-tete de tableur normalise (minuscule, sans accent ni separateur) => champ de l'eleve. */
    private const COLUMN_ALIASES = [
        'prenom' => 'first_name',
        'prenoms' => 'first_name',
        'nom' => 'last_name',
        'sexe' => 'gender',
        'genre' => 'gender',
        'datenaissance' => 'birth_date',
        'ddn' => 'birth_date',
        'classe' => 'class_name',
        'anneescolaire' => 'academic_year',
        'annee' => 'academic_year',
        'nomtuteur' => 'guardian_name',
        'tuteur' => 'guardian_name',
        'nomdututeur' => 'guardian_name',
        'telephonetuteur' => 'guardian_phone',
        'telephone' => 'guardian_phone',
        'tel' => 'guardian_phone',
        'emailtuteur' => 'guardian_email',
        'email' => 'guardian_email',
        'mail' => 'guardian_email',
        'adresse' => 'address',
    ];

    public function __construct(
        private readonly StudentService $students,
        private readonly AcademicYearService $years,
    ) {}

    /**
     * @return array{
     *     total: int, valid: int, invalid: int, dry_run: bool,
     *     errors: list<array{row: int, messages: list<string>}>,
     *     students: list<array{matricule: string, name: string, initial_password: string}>
     * }
     */
    public function import(UploadedFile $file, bool $dryRun): array
    {
        $rows = $this->parseRows($file);

        $errors = [];
        $students = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +1 pour l'en-tete, +1 pour repasser en indexation a 1.
            [$data, $rowErrors] = $this->validateRow($row);

            if ($rowErrors !== []) {
                $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];

                continue;
            }

            if ($dryRun) {
                continue;
            }

            try {
                $result = $this->students->enroll($data);
                $students[] = [
                    'matricule' => $result['student']->matricule,
                    'name' => $result['student']->fullName(),
                    'initial_password' => $result['initial_password'],
                ];
            } catch (Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'messages' => [$exception->getMessage()]];
            }
        }

        return [
            'total' => count($rows),
            'valid' => count($rows) - count($errors),
            'invalid' => count($errors),
            'dry_run' => $dryRun,
            'errors' => $errors,
            'students' => $students,
        ];
    }

    /** @return list<array<string, string>> une ligne = en-tetes reconnus => valeur brute */
    private function parseRows(UploadedFile $file): array
    {
        $sheet = IOFactory::createReaderForFile($file->getRealPath())
            ->load($file->getRealPath())
            ->getActiveSheet();

        $table = $sheet->toArray(null, true, true, false);

        if ($table === []) {
            return [];
        }

        $fieldsByColumn = [];
        foreach (array_shift($table) as $column => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if (isset(self::COLUMN_ALIASES[$normalized])) {
                $fieldsByColumn[$column] = self::COLUMN_ALIASES[$normalized];
            }
        }

        return array_values(array_map(
            fn (array $row) => array_reduce(
                array_keys($fieldsByColumn),
                function (array $carry, $column) use ($fieldsByColumn, $row) {
                    $carry[$fieldsByColumn[$column]] = trim((string) ($row[$column] ?? ''));

                    return $carry;
                },
                [],
            ),
            // Une ligne entierement vide (fin de tableau) ne doit pas produire une erreur fantome.
            array_filter($table, fn (array $row) => implode('', $row) !== ''),
        ));
    }

    private function normalizeHeader(string $header): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(trim($header))));
    }

    /**
     * @param  array<string, string>  $row
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validateRow(array $row): array
    {
        $data = [
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'gender' => strtoupper($row['gender'] ?? ''),
            'birth_date' => ($row['birth_date'] ?? '') === '' ? null : $row['birth_date'],
            'guardian_name' => $row['guardian_name'] ?? '',
            'guardian_phone' => $row['guardian_phone'] ?? '',
            'guardian_email' => ($row['guardian_email'] ?? '') === '' ? null : $row['guardian_email'],
            'address' => ($row['address'] ?? '') === '' ? null : $row['address'],
        ];

        $messages = [];

        $className = $row['class_name'] ?? '';
        if ($className !== '') {
            $academicYear = ($row['academic_year'] ?? '') !== '' ? $row['academic_year'] : $this->years->defaultYear();
            $schoolClass = $academicYear === null ? null : SchoolClass::query()
                ->where('name', $className)
                ->where('academic_year', $academicYear)
                ->first();

            if ($schoolClass === null) {
                $messages[] = "Classe introuvable : \"{$className}\"".($academicYear !== null ? " ({$academicYear})" : '').'.';
            } else {
                $data['school_class_id'] = $schoolClass->id;
            }
        }

        $validator = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['M', 'F'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'guardian_name' => ['required', 'string', 'max:150'],
            'guardian_phone' => ['required', 'string', 'max:30'],
            'guardian_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        return [$data, [...$messages, ...$validator->errors()->all()]];
    }
}
