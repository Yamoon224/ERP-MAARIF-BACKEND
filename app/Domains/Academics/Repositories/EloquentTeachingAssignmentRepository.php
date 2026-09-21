<?php

namespace App\Domains\Academics\Repositories;

use App\Domains\Academics\Contracts\TeachingAssignmentRepositoryContract;
use App\Models\ClassSubjectTeacher;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Collection;

final class EloquentTeachingAssignmentRepository implements TeachingAssignmentRepositoryContract
{
    public function forClass(SchoolClass $schoolClass): Collection
    {
        return ClassSubjectTeacher::query()
            ->where('school_class_id', $schoolClass->id)
            ->with(['subject', 'teacher:id,name'])
            ->get()
            ->sortBy(fn (ClassSubjectTeacher $row) => mb_strtolower($row->subject->name))
            ->values();
    }

    public function forTeacher(string $teacherId): Collection
    {
        return ClassSubjectTeacher::query()
            ->where('teacher_id', $teacherId)
            ->with(['schoolClass', 'subject'])
            ->get()
            ->sort(fn (ClassSubjectTeacher $a, ClassSubjectTeacher $b) => [$b->schoolClass->academic_year, $a->schoolClass->name, $a->subject->name]
                <=> [$a->schoolClass->academic_year, $b->schoolClass->name, $b->subject->name])
            ->values();
    }

    public function assign(SchoolClass $schoolClass, Subject $subject, string $teacherId): ClassSubjectTeacher
    {
        $assignment = ClassSubjectTeacher::query()->updateOrCreate(
            ['school_class_id' => $schoolClass->id, 'subject_id' => $subject->id],
            ['teacher_id' => $teacherId],
        );

        return $assignment->load(['subject', 'teacher:id,name']);
    }

    public function unassign(SchoolClass $schoolClass, Subject $subject): void
    {
        ClassSubjectTeacher::query()
            ->where('school_class_id', $schoolClass->id)
            ->where('subject_id', $subject->id)
            ->delete();
    }

    public function teachesStudent(string $teacherId, string $studentId, string $subjectId, string $academicYear): bool
    {
        return ClassSubjectTeacher::query()
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->whereIn(
                'school_class_id',
                Enrollment::query()
                    ->where('student_id', $studentId)
                    ->where('academic_year', $academicYear)
                    ->select('school_class_id'),
            )
            ->exists();
    }
}
