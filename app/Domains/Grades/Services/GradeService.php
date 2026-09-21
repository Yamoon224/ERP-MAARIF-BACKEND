<?php

namespace App\Domains\Grades\Services;

use App\Domains\Academics\Services\TeachingAssignmentService;
use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GradeService
{
    public function __construct(
        private readonly GradeRepositoryContract $grades,
        private readonly TeachingAssignmentService $assignments,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Grade>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->grades->paginate($filters, $perPage);
    }

    public function find(string $id): Grade
    {
        return $this->grades->findOrFail($id);
    }

    /**
     * Un enseignant ne saisit que dans ses matières et ses classes (voir
     * TeachingAssignmentService::assertMayGrade), il en va de même pour la
     * correction et la suppression d'une note.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data, User $author): Grade
    {
        $this->assignments->assertMayGrade($author, $data['student_id'], $data['subject_id'], $data['term_id']);

        return $this->grades->create([...$data, 'teacher_id' => $author->id]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Grade $grade, array $data, User $actor): Grade
    {
        $this->assignments->assertMayGrade($actor, $grade->student_id, $grade->subject_id, $grade->term_id);

        return $this->grades->update($grade, $data);
    }

    public function delete(Grade $grade, User $actor): void
    {
        $this->assignments->assertMayGrade($actor, $grade->student_id, $grade->subject_id, $grade->term_id);

        $this->grades->delete($grade);
    }
}
