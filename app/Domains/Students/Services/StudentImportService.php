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
 * Import en masse d'eleves depuis un tableur (CSV ou Excel) ou un export SQL,
 * pour migrer les donnees d'un systeme existant sans ressaisie manuelle.
 *
 * Un fichier .sql n'est jamais execute : il est seulement lu comme du texte
 * pour en extraire les instructions INSERT INTO reconnues (voir
 * parseSqlRows()). Executer tel quel le SQL d'un fichier importe exposerait
 * l'application a un contenu non maitrise (autre table, DROP, etc.) ; on se
 * contente donc d'en lire les valeurs, exactement comme pour une ligne de
 * tableur.
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
    /**
     * En-tete de tableur ou nom de colonne SQL normalise (minuscule, sans
     * accent ni separateur) => champ de l'eleve.
     */
    private const COLUMN_ALIASES = [
        'prenom' => 'first_name',
        'prenoms' => 'first_name',
        'firstname' => 'first_name',
        'nom' => 'last_name',
        'lastname' => 'last_name',
        'surname' => 'last_name',
        'sexe' => 'gender',
        'genre' => 'gender',
        'gender' => 'gender',
        'datenaissance' => 'birth_date',
        'ddn' => 'birth_date',
        'birthdate' => 'birth_date',
        'dateofbirth' => 'birth_date',
        'dob' => 'birth_date',
        'classe' => 'class_name',
        'class' => 'class_name',
        'classname' => 'class_name',
        'schoolclass' => 'class_name',
        'anneescolaire' => 'academic_year',
        'annee' => 'academic_year',
        'academicyear' => 'academic_year',
        'schoolyear' => 'academic_year',
        'nomtuteur' => 'guardian_name',
        'tuteur' => 'guardian_name',
        'nomdututeur' => 'guardian_name',
        'guardianname' => 'guardian_name',
        'parentname' => 'guardian_name',
        'telephonetuteur' => 'guardian_phone',
        'telephone' => 'guardian_phone',
        'tel' => 'guardian_phone',
        'phone' => 'guardian_phone',
        'guardianphone' => 'guardian_phone',
        'emailtuteur' => 'guardian_email',
        'email' => 'guardian_email',
        'mail' => 'guardian_email',
        'guardianemail' => 'guardian_email',
        'parentemail' => 'guardian_email',
        'adresse' => 'address',
        'address' => 'address',
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
        if (Str::lower((string) $file->getClientOriginalExtension()) === 'sql') {
            return $this->parseSqlRows($file);
        }

        return $this->parseSpreadsheetRows($file);
    }

    /** @return list<array<string, string>> une ligne = en-tetes reconnus => valeur brute */
    private function parseSpreadsheetRows(UploadedFile $file): array
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

    /**
     * Lit un export SQL (ex. mysqldump) comme du texte, sans jamais l'executer : on
     * en extrait uniquement les instructions INSERT INTO dont la liste de colonnes
     * couvre au moins un prenom et un nom reconnus — les autres tables d'un dump
     * complet (paiements, classes...) sont ignorees.
     *
     * @return list<array<string, string>> une ligne = en-tetes reconnus => valeur brute
     */
    private function parseSqlRows(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());
        if ($content === false || trim($content) === '') {
            return [];
        }

        $rows = [];

        foreach ($this->extractInsertStatements($content) as ['columns' => $columns, 'tuples' => $tuples]) {
            $fieldsByPosition = [];
            foreach ($columns as $index => $column) {
                $normalized = $this->normalizeHeader($column);
                if (isset(self::COLUMN_ALIASES[$normalized])) {
                    $fieldsByPosition[$index] = self::COLUMN_ALIASES[$normalized];
                }
            }

            if (! in_array('first_name', $fieldsByPosition, true) || ! in_array('last_name', $fieldsByPosition, true)) {
                continue;
            }

            foreach ($tuples as $tuple) {
                $row = [];
                foreach ($fieldsByPosition as $index => $field) {
                    $row[$field] = trim((string) ($tuple[$index] ?? ''));
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Decoupe le texte SQL en instructions INSERT INTO, sans jamais l'executer.
     *
     * @return list<array{columns: list<string>, tuples: list<list<string|null>>}>
     */
    private function extractInsertStatements(string $sql): array
    {
        $statements = $this->splitStatements($this->stripSqlComments($sql));
        $inserts = [];

        foreach ($statements as $statement) {
            if (! preg_match(
                '/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+[`"\[]?\w+[`"\]]?\s*\(([^)]*)\)\s*VALUES\s*(.+)$/is',
                trim($statement),
                $matches,
            )) {
                continue;
            }

            $columns = array_map(
                fn (string $column) => trim($column, " \t\n\r\0\x0B`\"'"),
                explode(',', $matches[1]),
            );

            $inserts[] = ['columns' => $columns, 'tuples' => $this->parseValueTuples($matches[2])];
        }

        return $inserts;
    }

    /** Retire les commentaires SQL (`-- ...` et `/* ... *\/`), pour ne pas les confondre avec des instructions. */
    private function stripSqlComments(string $sql): string
    {
        $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;

        return preg_replace('/--.*$/m', '', $sql) ?? $sql;
    }

    /**
     * Scinde un script SQL en instructions individuelles, sur les points-virgules qui
     * ne sont pas a l'interieur d'une chaine entre quotes (jamais executees : on ne fait
     * que reperer leurs limites).
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $quote = "'";
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $sql[++$i];

                    continue;
                }
                if ($char === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $current .= $sql[++$i];

                        continue;
                    }
                    $inString = false;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $quote = $char;
                $current .= $char;

                continue;
            }

            if ($char === ';') {
                $statements[] = $current;
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }

    /**
     * Decoupe la partie `VALUES (...), (...)` d'un INSERT en tuples de valeurs, en
     * respectant quotes et parentheses imbriquees dans les chaines.
     *
     * @return list<list<string|null>>
     */
    private function parseValueTuples(string $values): array
    {
        $tuples = [];
        $current = [];
        $token = '';
        $inString = false;
        $quote = "'";
        $depth = 0;
        $length = strlen($values);

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $token .= $values[++$i];

                    continue;
                }
                if ($char === $quote) {
                    if ($i + 1 < $length && $values[$i + 1] === $quote) {
                        $token .= $values[++$i];

                        continue;
                    }
                    $inString = false;

                    continue;
                }
                $token .= $char;

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = true;
                $quote = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    $current = [];
                    $token = '';
                }

                continue;
            }

            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $current[] = $this->finalizeSqlValue($token);
                    $tuples[] = $current;
                    $token = '';
                }

                continue;
            }

            if ($depth === 1 && $char === ',') {
                $current[] = $this->finalizeSqlValue($token);
                $token = '';

                continue;
            }

            if ($depth >= 1) {
                $token .= $char;
            }
        }

        return $tuples;
    }

    private function finalizeSqlValue(string $token): ?string
    {
        $trimmed = trim($token);

        return Str::upper($trimmed) === 'NULL' ? null : $trimmed;
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
