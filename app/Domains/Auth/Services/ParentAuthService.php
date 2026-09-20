<?php

namespace App\Domains\Auth\Services;

use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
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
    /**
     * @param  array{matricule: string, password: string}  $credentials
     * @return array{student: Student, token: string}
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, string $deviceName = 'web'): array
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
            'token' => $student->createToken($deviceName)->plainTextToken,
        ];
    }

    public function logout(Student $student): void
    {
        $student->currentAccessToken()->delete();
    }
}
