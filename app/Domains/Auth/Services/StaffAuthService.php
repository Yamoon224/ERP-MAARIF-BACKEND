<?php

namespace App\Domains\Auth\Services;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Authentification du personnel (administrateurs, enseignants) par jeton
 * Sanctum : le frontend Next.js et l'API sont sur des origines distinctes
 * sans session partagee.
 */
final class StaffAuthService
{
    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'web'): array
    {
        if (! Auth::validate($credentials)) {
            // Message identique que le compte existe ou non : distinguer les
            // deux cas transformerait le formulaire en oracle d'enumeration
            // de comptes.
            throw ValidationException::withMessages([
                'email' => ['Identifiants invalides.'],
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est desactive. Contactez un administrateur.'],
            ]);
        }

        $user->forceFill(['last_login_at' => Carbon::now()])->save();

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /**
     * @param  array{name: string, email: string, phone?: string|null}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    /**
     * Change le mot de passe et ferme les autres sessions : un mot de passe
     * change parce qu'il a fuite ne doit pas laisser un jeton vole ouvert.
     *
     * @throws ValidationException
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update(['password' => $newPassword]);

        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($tokens) => $tokens->where('id', '!=', $current->id))
            ->delete();
    }
}
