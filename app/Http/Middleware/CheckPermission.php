<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! auth('api')->check()) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        /** @var \App\Models\User|null $user */
        $user = auth('api')->user();

        if (! $user || ! $user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para realizar esta acción',
            ], 403);
        }

        return $next($request);
    }
}
