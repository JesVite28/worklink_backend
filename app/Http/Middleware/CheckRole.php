<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * Soporta:
     * - role:admin
     * - role:admin,empresa
     * - role:freelancer
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 401);
        }

        /*
         * Por seguridad, soporta tanto:
         * role:admin,empresa
         * como si llegara "admin,empresa" en un solo string.
         */
        $requiredRoles = collect($roles)
            ->flatMap(fn ($role) => explode(',', $role))
            ->map(fn ($role) => trim($role))
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($requiredRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'No se especificaron roles requeridos',
            ], 403);
        }

        $user->loadMissing('roles');

        if (! $user->hasAnyRole($requiredRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes los permisos necesarios',
                'required_roles' => $requiredRoles,
            ], 403);
        }

        return $next($request);
    }
}