<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\ChangePasswordRequest;
use App\Domains\Auth\Http\Requests\ParentForgotPasswordRequest;
use App\Domains\Auth\Http\Requests\ParentLoginRequest;
use App\Domains\Auth\Http\Requests\ParentResetPasswordRequest;
use App\Domains\Auth\Http\Resources\AuthenticatedStudentResource;
use App\Domains\Auth\Services\ParentAuthService;
use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentAuthController extends Controller
{
    public function __construct(private readonly ParentAuthService $auth) {}

    public function login(ParentLoginRequest $request): JsonResponse
    {
        $result = $this->auth->attempt(
            $request->only('matricule', 'password'),
            $request->input('device_name', 'web'),
            $request->boolean('remember'),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'student' => new AuthenticatedStudentResource($result['student']->load('schoolClass')),
            ],
        ]);
    }

    public function forgotPassword(ParentForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendPasswordResetLink($request->validated('matricule'));

        return response()->json([
            'message' => 'Si ce matricule existe, un message contenant un lien de réinitialisation vient d\'être envoyé au tuteur de l\'élève.',
        ]);
    }

    public function resetPassword(ParentResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->safe()->only(['matricule', 'token', 'password']));

        return response()->json(null, 204);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $this->auth->logout($student);

        return response()->json(null, 204);
    }

    public function me(Request $request): AuthenticatedStudentResource
    {
        return new AuthenticatedStudentResource($request->user()->load('schoolClass'));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();
        $this->auth->changePassword($student, $request->validated('current_password'), $request->validated('password'));

        return response()->json(null, 204);
    }
}
