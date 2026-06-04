<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticateApi
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Intentar obtener el token
            if (!JWTAuth::getToken()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Token requerido.'
                ], 401);
            }

            // Intentar autenticar con el token
            $user = JWTAuth::authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autorizado. Token inválido.'
                ], 401);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado.'
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido.'
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado. Token requerido.'
            ], 401);
        }

        return $next($request);
    }
}
