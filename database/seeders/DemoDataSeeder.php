<?php

namespace Database\Seeders;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Accounting\Services\PaymentService;
use App\Domains\Admissions\Enums\AdmissionStatus;
use App\Domains\Admissions\Services\AdmissionService;
use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\Discipline\Enums\SanctionType;
use App\Domains\Discipline\Enums\SummonStatus;
use App\Domains\Expenses\Services\ExpenseService;
use App\Domains\Grades\Enums\GradeType;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\NotificationType;
use App\Domains\Notifications\Services\GuardianNotifier;
use App\Domains\Results\Services\PromotionService;
use App\Domains\Results\Services\ResultsService;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\ExpenseCategory;
use App\Models\Grade;
use App\Models\NotificationLog;
use App\Models\Sanction;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Summon;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Jeu de demonstration : de quoi explorer l'application sans saisie manuelle.
 *
 * Deux annees scolaires (la precedente, terminee, et l'actuelle) calculees a
 * partir de la date du jour, pour que les filtres annee / trimestre / mois et
 * la comptabilite aient de quoi montrer. Les eleves de l'annee precedente
 * recoivent une decision de passage puis sont reinscrits pour l'annee
 * actuelle, et des candidatures d'admission sont deposees a chaque stade.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        [$admin, $teacher, $accountant] = $this->createStaff();

        $startYear = now()->month >= 9 ? now()->year : now()->year - 1;
        $previousYear = $this->createYear($startYear - 1);
        $currentYear = $this->createYear($startYear);

        $subjects = collect([
            ['name' => 'Mathematiques', 'code' => 'MATH', 'coefficient' => 4],
            ['name' => 'Francais', 'code' => 'FRAN', 'coefficient' => 4],
            ['name' => 'Anglais', 'code' => 'ANGL', 'coefficient' => 2],
            ['name' => 'Sciences de la vie', 'code' => 'SVT', 'coefficient' => 2],
        ])->map(fn (array $data) => Subject::create($data));

        $previousClass = $this->createClass('6eme A', '6eme', $previousYear['label'], 50000, $teacher, $subjects);
        $currentClass6 = $this->createClass('6eme A', '6eme', $currentYear['label'], 55000, $teacher, $subjects);
        $currentClass5 = $this->createClass('5eme A', '5eme', $currentYear['label'], 55000, $teacher, $subjects);

        $students = Student::factory()->count(10)->create(['school_class_id' => $previousClass->id]);

        $this->recordActivity($students, $previousYear['terms'], $subjects, $teacher, $admin);
        $this->recordPayments($students, $previousYear['label'], $accountant);

        // Fin d'annee : decisions de passage d'apres les moyennes annuelles, puis
        // reinscription (admis en 5eme, redoublants en 6eme, ancienne inscription conservee).
        app(ResultsService::class)->validateClassDecisions($previousClass, $admin->id);
        app(PromotionService::class)->promoteClass($previousClass, $currentClass5->id, $currentClass6->id);

        $newcomers = Student::factory()->count(5)->create(['school_class_id' => $currentClass6->id]);
        $this->recordActivity($students->concat($newcomers), $currentYear['terms'], $subjects, $teacher, $admin);
        $this->recordPayments($students->concat($newcomers), $currentYear['label'], $accountant);

        $this->recordExpenses($startYear - 1, $accountant, endsOn: Carbon::create($startYear, 6, 30));
        $this->recordExpenses($startYear, $accountant, endsOn: Carbon::now()->startOfDay());

        $this->recordFailedNotification($newcomers->first());
        $this->createAdmissions($currentYear['label'], $currentClass6, $admin);
    }

    /**
     * Achats et depenses courantes de l'annee : fournitures avant la rentree,
     * puis factures et reparations reparties jusqu'a `$endsOn` (aujourd'hui pour
     * l'annee en cours : une depense n'est pas datee dans le futur).
     */
    private function recordExpenses(int $startYear, User $accountant, Carbon $endsOn): void
    {
        $this->call(ExpenseCategorySeeder::class);

        $categories = ExpenseCategory::query()->pluck('id', 'name');
        $expenses = app(ExpenseService::class);

        // [categorie, designation, fournisseur, quantite, unite, prix unitaire, mode de paiement]
        $purchases = [
            ['Fournitures scolaires', 'Craies blanches (boîtes de 100)', 'Papeterie Centrale', 40, 'boîte', 12000, PaymentMethod::Cash],
            ['Registres et imprimés', "Registres d'appel", 'Imprimerie Nationale', 12, 'registre', 35000, PaymentMethod::BankTransfer],
            ['Fournitures scolaires', 'Craies de couleur', 'Papeterie Centrale', 15, 'boîte', 18000, PaymentMethod::Cash],
            ['Fournitures scolaires', 'Marqueurs pour tableau blanc', 'Papeterie Centrale', 30, 'unité', 6500, PaymentMethod::MobileMoney],
            ['Registres et imprimés', 'Cahiers de textes', 'Imprimerie Nationale', 20, 'cahier', 9000, PaymentMethod::Cash],
            ['Entretien et réparations', 'Produits et balais de nettoyage', 'Marché Madina', 1, 'lot', 145000, PaymentMethod::Cash],
            ['Eau, électricité et communications', "Facture d'électricité", "Compagnie d'électricité", 1, 'facture', 380000, PaymentMethod::MobileMoney],
            ['Mobilier et équipement', 'Réparation de bancs', 'Menuiserie Diallo', 8, 'banc', 45000, PaymentMethod::Cash],
            ['Matériel pédagogique', 'Cartes murales de géographie', 'Librairie du Savoir', 6, 'carte', 85000, PaymentMethod::Cheque],
            ['Eau, électricité et communications', "Facture d'eau", 'Compagnie des eaux', 1, 'facture', 95000, PaymentMethod::MobileMoney],
            ['Fournitures scolaires', 'Ramettes de papier A4', 'Papeterie Centrale', 25, 'ramette', 42000, PaymentMethod::BankTransfer],
        ];

        $start = Carbon::create($startYear, 9, 1)->startOfDay();
        $span = max($start->diffInDays($endsOn), 0);

        foreach ($purchases as $index => [$category, $label, $supplier, $quantity, $unit, $unitPrice, $method]) {
            $expenses->register([
                'expense_category_id' => $categories[$category],
                'label' => $label,
                'supplier_name' => $supplier,
                'quantity' => $quantity,
                'unit' => $unit,
                'unit_price' => $unitPrice,
                'method' => $method->value,
                'spent_at' => $start->copy()->addDays(intdiv($span * $index, count($purchases) - 1))->toDateString(),
            ], $accountant->id);
        }
    }

    /**
     * Candidatures a tous les stades du parcours : deux deposees, une en etude,
     * une admise, une en liste d'attente, une refusee et une deja inscrite. Elles
     * passent par le service, comme en production, donc les tuteurs sont
     * notifies (voir le journal des notifications).
     */
    private function createAdmissions(string $academicYear, SchoolClass $class, User $admin): void
    {
        $admissions = app(AdmissionService::class);

        $submit = fn (string $first, string $last, string $gender) => $admissions->submit([
            'academic_year' => $academicYear,
            'level' => '6eme',
            'first_name' => $first,
            'last_name' => $last,
            'gender' => $gender,
            'birth_date' => fake()->dateTimeBetween('-12 years', '-10 years')->format('Y-m-d'),
            'previous_school' => 'Ecole primaire '.fake()->lastName(),
            'guardian_name' => fake()->name(),
            'guardian_phone' => '+224'.fake()->numerify('6########'),
            'guardian_email' => fake()->safeEmail(),
            'address' => 'Conakry',
        ]);

        $submit('Mariama', 'Barry', 'F');
        $submit('Ibrahima', 'Sow', 'M');

        $admissions->changeStatus($submit('Fatoumata', 'Keita', 'F'), AdmissionStatus::UnderReview, null, $admin->id);
        $admissions->changeStatus($submit('Ousmane', 'Diallo', 'M'), AdmissionStatus::Accepted, 'Dossier complet', $admin->id);
        $admissions->changeStatus($submit('Aissatou', 'Camara', 'F'), AdmissionStatus::Waitlisted, 'Classe complete pour le moment', $admin->id);
        $admissions->changeStatus($submit('Mamadou', 'Bangoura', 'M'), AdmissionStatus::Rejected, 'Niveau insuffisant a l\'entretien', $admin->id);

        $enrolled = $admissions->changeStatus($submit('Kadiatou', 'Toure', 'F'), AdmissionStatus::Accepted, null, $admin->id);
        $admissions->enroll($enrolled, $class->id);
    }

    /** @return array{User, User, User} */
    private function createStaff(): array
    {
        $accounts = [
            ['Admin Maarif', 'admin@maarif.test', '+224600000001', 'admin'],
            ['Mariam Diallo', 'enseignant@maarif.test', '+224600000002', 'teacher'],
            ['Souleymane Bah', 'comptable@maarif.test', '+224600000003', 'accountant'],
        ];

        return array_map(function (array $account): User {
            [$name, $email, $phone, $role] = $account;

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);
            $user->assignRole($role);

            return $user;
        }, $accounts);
    }

    /**
     * Trois trimestres de trois mois (octobre a juin : neuf mois de scolarite).
     * Le trimestre courant est celui qui contient aujourd'hui ; avant la
     * rentree, c'est le premier trimestre de l'annee en cours.
     *
     * @return array{label: string, terms: Collection<int, Term>}
     */
    private function createYear(int $startYear): array
    {
        $label = $startYear.'-'.($startYear + 1);
        $windows = [
            ['1er trimestre', "{$startYear}-10-01", "{$startYear}-12-31"],
            ['2eme trimestre', ($startYear + 1).'-01-01', ($startYear + 1).'-03-31'],
            ['3eme trimestre', ($startYear + 1).'-04-01', ($startYear + 1).'-06-30'],
        ];

        $terms = collect($windows)->map(fn (array $window) => Term::create([
            'name' => $window[0],
            'academic_year' => $label,
            'starts_at' => $window[1],
            'ends_at' => $window[2],
            'is_current' => false,
        ]));

        $isCurrentYear = $startYear === (now()->month >= 9 ? now()->year : now()->year - 1);
        if ($isCurrentYear) {
            $current = $terms->first(fn (Term $term) => Carbon::now()->between($term->starts_at->startOfDay(), $term->ends_at->endOfDay())) ?? $terms->first();
            $current->update(['is_current' => true]);
        }

        return ['label' => $label, 'terms' => $terms];
    }

    /** @param  Collection<int, Subject>  $subjects */
    private function createClass(string $name, string $level, string $year, int $fee, User $teacher, Collection $subjects): SchoolClass
    {
        $class = SchoolClass::create([
            'name' => $name,
            'level' => $level,
            'academic_year' => $year,
            'monthly_fee' => $fee,
            'main_teacher_id' => $teacher->id,
        ]);

        $subjects->each(fn (Subject $subject) => $class->subjects()->attach($subject->id, ['teacher_id' => $teacher->id]));

        return $class;
    }

    /**
     * Notes, presences, une sanction et une convocation pour chaque trimestre
     * deja commence ; rien pour un trimestre a venir.
     *
     * @param  Collection<int, Student>  $students
     * @param  Collection<int, Term>  $terms
     * @param  Collection<int, Subject>  $subjects
     */
    private function recordActivity(Collection $students, Collection $terms, Collection $subjects, User $teacher, User $admin): void
    {
        $today = Carbon::now()->startOfDay();

        foreach ($terms as $term) {
            if ($term->starts_at->startOfDay()->greaterThan($today)) {
                continue;
            }

            $last = $term->ends_at->startOfDay()->min($today);
            $span = max(1, (int) $term->starts_at->startOfDay()->diffInDays($last));

            foreach ($students as $student) {
                foreach ($subjects as $subject) {
                    Grade::create([
                        'student_id' => $student->id,
                        'subject_id' => $subject->id,
                        'term_id' => $term->id,
                        'teacher_id' => $teacher->id,
                        'type' => GradeType::Devoir,
                        'value' => fake()->randomFloat(2, 8, 20),
                        'max_value' => 20,
                        'recorded_at' => $term->starts_at->copy()->addDays(fake()->numberBetween(0, $span))->toDateString(),
                    ]);
                }

                foreach (range(1, 3) as $ignored) {
                    $date = $term->starts_at->copy()->addDays(fake()->numberBetween(0, $span));
                    if ($date->isWeekend()) {
                        continue;
                    }

                    AttendanceRecord::firstOrCreate(
                        ['student_id' => $student->id, 'date' => $date->toDateString()],
                        [
                            'status' => fake()->randomElement([AttendanceStatus::Absent, AttendanceStatus::Late]),
                            'justified' => fake()->boolean(40),
                            'reason' => fake()->boolean(40) ? 'Maladie' : null,
                            'recorded_by' => $teacher->id,
                        ],
                    );
                }
            }

            // Comme en production, la creation previent le tuteur : le journal des
            // notifications a ainsi des convocations et des sanctions a montrer.
            $notifier = app(GuardianNotifier::class);

            $sanction = Sanction::create([
                'student_id' => $students->random()->id,
                'type' => SanctionType::Warning,
                'reason' => 'Bavardages repetes en classe',
                'start_date' => $term->starts_at->copy()->addDays(fake()->numberBetween(0, $span))->toDateString(),
                'created_by' => $admin->id,
            ]);
            $sanction->update(['notified_at' => $notifier->notifySanction($sanction)->sent_at]);

            $summon = Summon::create([
                'student_id' => $students->random()->id,
                'reason' => 'Resultats en baisse',
                'scheduled_at' => $term->starts_at->copy()->addDays(fake()->numberBetween(0, $span))->setTime(9, 0),
                'location' => 'Bureau de la direction',
                'status' => SummonStatus::Done,
                'created_by' => $admin->id,
            ]);
            $summon->update(['notified_at' => $notifier->notifySummon($summon)->sent_at]);
        }
    }

    /** Un message en echec, pour que le journal montre aussi ce cas : un SMS que l'operateur a refuse. */
    private function recordFailedNotification(Student $student): void
    {
        NotificationLog::create([
            'student_id' => $student->id,
            'channel' => NotificationChannel::Sms,
            'type' => NotificationType::Summon,
            'recipient' => $student->guardian_phone,
            'subject' => 'Convocation - '.$student->fullName(),
            'body' => 'Convocation pour '.$student->fullName().'. Motif : Resultats en baisse.',
            'status' => NotificationStatus::Failed,
            'error' => 'Operateur SMS indisponible',
        ]);
    }

    /**
     * Des familles reglent l'annee, d'autres le trimestre ou le mois, d'autres
     * pas du tout : la comptabilite a ainsi des impayes a montrer.
     *
     * @param  Collection<int, Student>  $students
     */
    private function recordPayments(Collection $students, string $academicYear, User $accountant): void
    {
        $payments = app(PaymentService::class);
        $formulas = [PaymentPeriod::Annual, PaymentPeriod::Semiannual, PaymentPeriod::Quarterly, PaymentPeriod::Monthly, null];
        $today = Carbon::now()->startOfDay();

        foreach ($students->values() as $index => $student) {
            $period = $formulas[$index % count($formulas)];
            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year', $academicYear)
                ->first();

            if ($period === null || $enrollment === null) {
                continue;
            }

            // Un paiement ne peut pas etre date dans le futur : avant la
            // rentree, les recus sont dates du jour.
            $yearStart = Carbon::create((int) substr($academicYear, 0, 4), 10, 1)->startOfDay();
            $paidAt = $yearStart->min($today)->toDateString();

            $payments->register($enrollment, $period, [
                'method' => fake()->randomElement(PaymentMethod::cases())->value,
                'paid_at' => $paidAt,
            ], $accountant->id);
        }
    }
}
