<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Services\ActivityLoggerService;
use App\Models\User;
use App\Models\Role;

/**
 * @OA\Info(
 *     title="API WorkLink",
 *     version="1.0.0",
 *     description="Documentación de la API REST"
 * )
 *
 * @OA\Server(
 *     url="http://127.0.0.1:8000",
 *     description="Servidor local"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Autenticación y manejo de sesión"
 * )
 *
 * @OA\Tag(
 *     name="Users",
 *     description="Gestión de usuarios"
 * )
 *
 * @OA\Tag(
 *     name="Roles",
 *     description="Gestión de roles"
 * )
 *
 * @OA\Tag(
 *     name="Activity Logs",
 *     description="Endpoints para visualizar activity logs"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/login",
     *     operationId="login",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@worklink.com"),
     *             @OA\Property(property="password", type="string", example="admin123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login exitoso, retorna JWT token"),
     *     @OA\Response(response=401, description="Credenciales inválidas")
     * )
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $credentials = $request->only('email', 'password');

        /** @var \Tymon\JWTAuth\JWTGuard $guard */
        $guard = auth('api');

        try {
            if (! $token = $guard->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales inválidas',
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el token',
            ], 500);
        }

        $user = $guard->user();
        $user->load('roles');

        // Registrar actividad
        ActivityLoggerService::logLogin($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'token' => $token,
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
                'rol' => $user->roles->first() ? [
                    'id' => $user->roles->first()->id,
                    'nombre' => $user->roles->first()->nombre,
                ] : null,
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     operationId="me",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Datos del usuario autenticado",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function me()
    {
        /** @var \App\Models\User|null $user */
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
            ], 401);
        }

        $user->load('roles');

        return response()->json([
            'success' => true,
            'message' => 'Datos del usuario',
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
                'activo' => $user->activo,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'nombre' => $role->nombre,
                ])->toArray(),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logout",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Sesión cerrada exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function logout()
    {
        $user = auth('api')->user();

        try {
            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = auth('api');
            $guard->logout();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo cerrar sesión',
            ], 500);
        }

        // Registrar actividad
        if ($user) {
            ActivityLoggerService::logLogout($user->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     operationId="refresh",
     *     tags={"Auth"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refrescado exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function refresh()
    {
        try {
            /** @var \Tymon\JWTAuth\JWTGuard $guard */
            $guard = auth('api');
            $newToken = $guard->refresh();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo refrescar el token',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token refrescado',
            'data' => ['token' => $newToken],
        ]);
    }

    /**
     * Registrar un nuevo usuario.
     *
     * @OA\Post(
     *     path="/api/register",
     *     operationId="register",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"nombre","apellido","email","password","tipo_cuenta"},
     *             @OA\Property(property="nombre", type="string"),
     *             @OA\Property(property="apellido", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="tipo_cuenta", type="string", enum={"Cliente","Freelancer","Empresa"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario registrado exitosamente"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:100',
            'apellido' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'tipo_cuenta' => 'required|in:Cliente,Freelancer,Empresa',
        ]);

        // Crear usuario
        $user = User::create([
            'nombre' => $validated['nombre'],
            'apellido' => $validated['apellido'],
            'email' => $validated['email'],
            'password_hash' => Hash::make($validated['password']),
            'tipo_cuenta' => $validated['tipo_cuenta'],
            'activo' => true,
        ]);

        // Asignar roles por defecto según tipo de cuenta
        $roles = [Role::where('nombre', 'user')->first()?->id];

        if ($validated['tipo_cuenta'] === 'Freelancer') {
            $roles[] = Role::where('nombre', 'freelancer')->first()?->id;
        } elseif ($validated['tipo_cuenta'] === 'Empresa') {
            $roles[] = Role::where('nombre', 'empresa')->first()?->id;
        }

        $roles = array_filter($roles);
        if (!empty($roles)) {
            $user->roles()->attach($roles);
        }

        // Registrar actividad
        ActivityLoggerService::logRegister($user->id, $user->email);

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado exitosamente',
            'data' => [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'apellido' => $user->apellido,
                'email' => $user->email,
                'tipo_cuenta' => $user->tipo_cuenta,
            ]
        ], 201);
    }
}
