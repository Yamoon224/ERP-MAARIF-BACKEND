<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Contracts\StudentRepositoryContract;
use App\Domains\Students\Support\MatriculeGenerator;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Inscription et gestion des eleves (cahier des charges 3.4).
 *
 * L'inscription attribue elle-meme le matricule et un mot de passe initial :
 * ni l'un ni l'autre ne sont saisis par l'administrateur, pour que le
 * matricule reste effectivement genere par le systeme (cahier des charges 2)
 * et non choisi de facon previsible ("eleve001").
 */
final class StudentService
{
    public function __construct(private readonly StudentRepositoryContract $students) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Student>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->students->paginate($filters, $perPage);
    }

    public function find(string $id): Student
    {
        return $this->students->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{student: Student, initial_password: string}
     */
    public function enroll(array $data): array
    {
        $initialPassword = Str::password(10, symbols: false);

        $student = $this->students->create([
            ...$data,
            'matricule' => MatriculeGenerator::generate(),
            'password' => $initialPassword,
            'is_active' => true,
        ]);

        return ['student' => $student, 'initial_password' => $initialPassword];
    }

    /** @param  array<string, mixed>  $data */
    public function update(Student $student, array $data): Student
    {
        return $this->students->update($student, $data);
    }

    /** Reinitialise le mot de passe du portail parent, ex. apres une perte. */
    public function resetPassword(Student $student): string
    {
        $newPassword = Str::password(10, symbols: false);

        $this->students->update($student, ['password' => $newPassword]);

        return $newPassword;
    }

    public function delete(Student $student): void
    {
        $this->students->delete($student);
    }
}
