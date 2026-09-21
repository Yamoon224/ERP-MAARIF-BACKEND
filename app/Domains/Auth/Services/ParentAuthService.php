<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Support\SessionLifetime;
use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Models\PersonalAccessToken;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Authentification du parent par le matricule et le mot de passe de son
 * enfant (cahier des charges 3.1).
 *
 * `Student` n'est rattache a aucun "provider" du fichier config/auth.php : il
 * est verifie ici a la main, puis authentifie par un jeton Sanctum comme un
 * `User` ordinaire. Le middleware `auth:sanctum` n'a besoin de connaitre que
 * le jeton, jamais le modele qui l'a emis (voir Laravel\Sanctum\Guard).
 */
final class ParentAuthService
{
    public function __construct(private readonly NotificationSenderContract $sender) {}

    /**
     * @param  array{matricule: string, password: string}  $credentials
     * @param  bool  $remember  "Se souvenir de moi" : allonge la duree de vie du jeton
     * @return array{student: Student, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'web', bool $remember = false): array
    {
        $student = Student::where('matricule', $credentials['matricule'])->first();

        // Meme message que le matricule existe ou non : distinguer les deux
        // cas transformerait le formulaire en oracle de matricules valides.
        if ($student === null || ! Hash::check($credentials['password'], $student->password)) {
            throw ValidationException::withMessages([
                'matricule' => ['Matricule ou mot de passe incorrect.'],
            ]);
        }

        if (! $student->is_active) {
            throw ValidationException::withMessages([
                'matricule' => ['Ce dossier eleve est desactive. Contactez l\'etablissement.'],
            ]);
        }

        $student->forceFill(['last_login_at' => Carbon::now()])->save();

        return [
            'student' => $student,
            'token' => $student->createToken($deviceName, ['*'], SessionLifetime::expiresAt($remember))->plainTextToken,
        ];
    }

    /**
     * Envoie au tuteur, par e-mail (sinon par SMS), un lien pour choisir un
     * nouveau mot de passe du portail. Le mot de passe ne change qu'une fois le
     * lien ouvert : n'importe qui connaissant un matricule peut faire la demande,
     * il ne doit pas pouvoir enfermer dehors le vrai parent. Reponse identique
     * que le matricule existe ou non (pas d'oracle de matricules).
     *
     * Le message n'est pas consigne au journal des notifications : le lien y
     * serait lisible par tout le personnel qui le consulte.
     */
    public function sendPasswordResetLink(string $matricule): void
    {
        Password::broker('students')->sendResetLink(['matricule' => $matricule], function (Student $student, string $token): bool {
            if ($student->is_active) {
                $byEmail = $student->guardian_email !== null;

                $this->sender->send(new NotificationMessage(
                    channel: $byEmail ? NotificationChannel::Email : NotificationChannel::Sms,
                    recipient: $byEmail ? $student->guardian_email : $student->guardian_phone,
                    body: $this->resetMessage($student, $token),
                    subject: 'Réinitialisation du mot de passe du portail parent',
                ));
            }

            return true;
        });
    }

    /**
     * Definit un nouveau mot de passe a partir du lien recu par le tuteur, et
     * ferme les sessions ouvertes du portail.
     *
     * @param  array{matricule: string, token: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker('students')->reset($data, function (Student $student, string $password): void {
            $student->update(['password' => $password]);
            $student->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'matricule' => ['Ce lien de réinitialisation est invalide ou a expiré.'],
            ]);
        }
    }

    private function resetMessage(Student $student, string $token): string
    {
        $url = rtrim(config('app.frontend_url'), '/').'/portal/reset-password?'.http_build_query(['token' => $token, 'matricule' => $student->matricule]);
        $minutes = config('auth.passwords.students.expire');

        return "Portail parent ERP Maarif : la réinitialisation du mot de passe de {$student->fullName()} ({$student->matricule}) a été demandée. "
            ."Ouvrez ce lien pour en choisir un nouveau (valable {$minutes} minutes) : {$url} "
            .'Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message.';
    }

    public function logout(Student $student): void
    {
        $student->currentAccessToken()->delete();
    }

    /**
     * Change le mot de passe du portail et ferme les autres sessions.
     *
     * @throws ValidationException
     */
    public function changePassword(Student $student, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $student->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $student->update(['password' => $newPassword]);

        $current = $student->currentAccessToken();
        $student->tokens()
            ->when($current instanceof PersonalAccessToken, fn ($tokens) => $tokens->where('id', '!=', $current->id))
            ->delete();
    }
}
