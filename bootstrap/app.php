<?php

use App\Domains\Shared\Exceptions\DomainException;
use App\Http\Middleware\EnsureTokenOwnerType;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'account_type' => EnsureTokenOwnerType::class,
        ]);

        // API pure, sans page de login web : ne jamais rediriger un invite
        // vers une route nommee `login` qui n'existe pas.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Contrat de reponse d'erreur, uniforme sur toute l'API :
         *   { "message": string, "error_code": string, "context": object }
         * enrichi de "errors" pour les erreurs de validation (422).
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'error_code' => 'validation_failed',
                'errors' => $exception->errors(),
                'context' => (object) [],
            ], 422);
        });

        $exceptions->render(function (DomainException $exception, Request $request) {
            return $request->is('api/*') || $request->expectsJson()
                ? $exception->render()
                : null;
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Non authentifie — reconnectez-vous.',
                'error_code' => 'unauthenticated',
                'context' => (object) [],
            ], 401);
        });

        $exceptions->render(function (UnauthorizedException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => "Vous n'avez pas les droits necessaires pour cette action.",
                'error_code' => 'forbidden',
                'context' => (object) ['required_permissions' => $exception->getRequiredPermissions()],
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'error_code' => 'not_found',
                'context' => (object) [],
            ], 404);
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Ressource introuvable.',
                'error_code' => 'not_found',
                'context' => (object) [],
            ], 404);
        });

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Trop de tentatives. Patientez avant de reessayer.',
                'error_code' => 'too_many_requests',
                'context' => (object) [],
            ], 429, $exception->getHeaders());
        });
    })->create();
