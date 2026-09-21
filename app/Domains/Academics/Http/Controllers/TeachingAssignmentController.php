<?php

namespace App\Domains\Academics\Http\Controllers;

use App\Domains\Academics\Http\Requests\AssignTeacherRequest;
use App\Domains\Academics\Http\Resources\TeachingAssignmentResource;
use App\Domains\Academics\Services\TeachingAssignmentService;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/** Affectation des enseignants : pour chaque matière d'une classe, qui l'enseigne. */
class TeachingAssignmentController extends Controller
{
    public function __construct(private readonly TeachingAssignmentService $assignments) {}

    public function index(SchoolClass $schoolClass): AnonymousResourceCollection
    {
        return TeachingAssignmentResource::collection($this->assignments->forClass($schoolClass));
    }

    /** Ce que l'utilisateur connecté enseigne (vide pour un compte qui n'enseigne pas). */
    public function mine(Request $request): AnonymousResourceCollection
    {
        return TeachingAssignmentResource::collection($this->assignments->forTeacher($request->user()));
    }

    /** Affecte, ou remplace, l'enseignant de cette matière dans cette classe. */
    public function update(AssignTeacherRequest $request, SchoolClass $schoolClass, Subject $subject): TeachingAssignmentResource
    {
        return new TeachingAssignmentResource(
            $this->assignments->assign($schoolClass, $subject, $request->validated('teacher_id')),
        );
    }

    public function destroy(SchoolClass $schoolClass, Subject $subject): Response
    {
        $this->assignments->unassign($schoolClass, $subject);

        return response()->noContent();
    }
}
