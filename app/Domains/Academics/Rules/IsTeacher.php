<?php

namespace App\Domains\Academics\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Le compte désigné doit exister et avoir le rôle enseignant (pas un comptable, ni un administrateur seul). */
final class IsTeacher implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = is_string($value) ? User::query()->find($value) : null;

        if ($user === null || ! $user->hasRole('teacher')) {
            $fail("Ce compte n'a pas le rôle enseignant.");
        }
    }
}
