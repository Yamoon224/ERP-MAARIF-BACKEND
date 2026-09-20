<?php

namespace App\Http\Middleware;

use App\Models\Student;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloisonne les routes selon le type de compte porteur du jeton.
 *
 * `auth:sanctum` authentifie indifferemment un `User` (personnel) ou un
 * `Student` (portail parent) : le jeton ne porte que l'identite de son
 * emetteur, pas la zone de l'API a laquelle il donne droit. Sans ce
 * middleware, un jeton parent valide pourrait interroger les routes
 * d'administration, et reciproquement.
 */
class EnsureTokenOwnerType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();

        $expectedClass = match ($type) {
            'staff' => User::class,
            'parent' => Student::class,
            default => throw new \InvalidArgumentException("Type de compte inconnu : {$type}"),
        };

        if (! $user instanceof $expectedClass) {
            return response()->json([
                'message' => "Vous n'avez pas les droits necessaires pour cette action.",
                'error_code' => 'forbidden',
                'context' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
