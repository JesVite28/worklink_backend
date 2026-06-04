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
     * Soporta sintaxis:
     * - role:admin          (usuario debe tener el rol 'admin')
     * - role:admin,empresa  (usuario debe tener al menos uno de los roles)
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $roles  Roles requeridos separados por coma
     */
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        if (! auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        /** @var \App\Models\User|null $user */
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 401);
        }

        // Convertir roles en array
        $requiredRoles = array_map('trim', explode(',', $roles));

        // Verificar si el usuario tiene al menos uno de los roles requeridos
        if (! $user->hasAnyRole($requiredRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes los permisos necesarios. Roles requeridos: ' . $roles,
            ], 403);
        }

        return $next($request);
    }
}

