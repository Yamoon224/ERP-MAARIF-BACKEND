<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Support\SessionLifetime;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Authentification du personnel (administrateurs, enseignants) par jeton
 * Sanctum : le frontend Next.js et l'API sont sur des origines distinctes
 * sans session partagee.
 */
final class StaffAuthService
{
    public function __construct(private readonly NotificationSenderContract $sender) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     * @param  bool  $remember  "Se souvenir de moi" : allonge la duree de vie du jeton
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'web', bool $remember = false): array
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
            'token' => $user->createToken($deviceName, ['*'], SessionLifetime::expiresAt($remember))->plainTextToken,
        ];
    }

    /**
     * Envoie a l'adresse e-mail du compte un lien de reinitialisation du mot de
     * passe. Ne dit jamais si le compte existe : la reponse est la meme pour une
     * adresse inconnue, un compte desactive ou une demande trop rapprochee,
     * sinon ce formulaire public listerait les comptes du personnel.
     */
    public function sendPasswordResetLink(string $email): void
    {
        Password::broker('users')->sendResetLink(['email' => $email], function (User $user, string $token): bool {
            if ($user->is_active) {
                $this->sender->send(new NotificationMessage(
                    channel: NotificationChannel::Email,
                    recipient: $user->email,
                    body: $this->resetMessage($user, $token),
                    subject: 'Réinitialisation de votre mot de passe',
                ));
            }

            return true;
        });
    }

    /**
     * Definit un nouveau mot de passe a partir du lien recu par e-mail, et ferme
     * toutes les sessions ouvertes : le mot de passe est reinitialise parce qu'il
     * est perdu ou compromis.
     *
     * @param  array{email: string, token: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('users')->reset($data, function (User $user, string $password): void {
            $user->update(['password' => $password]);
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['Ce lien de réinitialisation est invalide ou a expiré.'],
            ]);
        }
    }

    private function resetMessage(User $user, string $token): string
    {
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]);
        $minutes = config('auth.passwords.users.expire');

        return "Bonjour {$user->name},\n\n"
            ."Vous avez demandé la réinitialisation de votre mot de passe ERP Maarif. Ouvrez ce lien pour en choisir un nouveau (valable {$minutes} minutes) :\n\n"
            ."{$url}\n\n"
            .'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.';
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
