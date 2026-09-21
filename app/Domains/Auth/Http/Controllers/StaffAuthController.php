<?php

namespace App\Domains\Auth\Http\Controllers;

use App\Domains\Auth\Http\Requests\ChangePasswordRequest;
use App\Domains\Auth\Http\Requests\ForgotPasswordRequest;
use App\Domains\Auth\Http\Requests\ResetPasswordRequest;
use App\Domains\Auth\Http\Requests\StaffLoginRequest;
use App\Domains\Auth\Http\Requests\UpdateProfileRequest;
use App\Domains\Auth\Http\Resources\AuthenticatedStaffResource;
use App\Domains\Auth\Services\StaffAuthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAuthController extends Controller
{
    public function __construct(private readonly StaffAuthService $auth) {}

    public function login(StaffLoginRequest $request): JsonResponse
    {
        $result = $this->auth->attempt(
            $request->only('email', 'password'),
            $request->input('device_name', 'web'),
            $request->boolean('remember'),
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'user' => new AuthenticatedStaffResource($result['user']),
            ],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendPasswordResetLink($request->validated('email'));

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un e-mail contenant un lien de réinitialisation vient d\'être envoyé.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->safe()->only(['email', 'token', 'password']));

        return response()->json(null, 204);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return response()->json(null, 204);
    }

    public function me(Request $request): AuthenticatedStaffResource
    {
        return new AuthenticatedStaffResource($request->user());
    }

    public function updateProfile(UpdateProfileRequest $request): AuthenticatedStaffResource
    {
        return new AuthenticatedStaffResource($this->auth->updateProfile($request->user(), $request->validated()));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return response()->json(null, 204);
    }
}
