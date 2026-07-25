<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Info(
 *     title="API WorkLink",
 *     version="1.0.0",
 *     description="Documentación de la API REST"
 * )
 *
 * @OA\Server(
 *     url="https://worklinkbackend-production-16dd.up.railway.app",
 *     description="Servidor produccion"
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
     *     summary="Iniciar sesión",
     *     description="Inicia sesión y retorna un token JWT",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@worklink.com"),
     *             @OA\Property(property="password", type="string", example="admin123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login exitoso"),
     *     @OA\Response(response=401, description="Credenciales inválidas"),
     *     @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $guard = auth('api');

        try {
            if (! $token = $guard->attempt($validated)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales inválidas',
                ], 401);
            }
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el token',
                'error' => $e->getMessage(),
            ], 500);
        }

        $user = $guard->user();
        $user->load('roles');

        ActivityLoggerService::logLogin($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/register",
     *     operationId="register",
     *     tags={"Auth"},
     *     summary="Registrar usuario",
     *     description="Registra un nuevo usuario, asigna un solo rol principal y permite subir una foto de perfil opcional. El rol admin no puede registrarse desde este endpoint.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","last_name","email","password","password_confirmation","role"},
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     maxLength=100,
     *                     example="Adrian"
     *                 ),
     *                 @OA\Property(
     *                     property="last_name",
     *                     type="string",
     *                     maxLength=100,
     *                     example="Vite"
     *                 ),
     *                 @OA\Property(
     *                     property="maternal_last_name",
     *                     type="string",
     *                     nullable=true,
     *                     maxLength=100,
     *                     example="Espinosa"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     maxLength=150,
     *                     example="adrian@test.com"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     type="string",
     *                     nullable=true,
     *                     maxLength=20,
     *                     example="7712233445"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     minLength=8,
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string",
     *                     enum={"cliente","freelancer","empresa"},
     *                     example="freelancer"
     *                 ),
     *                 @OA\Property(
     *                     property="profile_photo",
     *                     type="string",
     *                     format="binary",
     *                     nullable=true,
     *                     description="Foto de perfil opcional. Formatos permitidos: jpg, jpeg, png o webp. Tamaño máximo: 2MB."
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Usuario registrado exitosamente"),
     *     @OA\Response(response=422, description="Datos inválidos"),
     *     @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function register(Request $request)
    {
        $photoPath = null;

        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'maternal_last_name' => 'nullable|string|max:100',
                'email' => [
                    'required',
                    'email',
                    'max:150',
                    Rule::unique('users', 'email')
                        ->whereNull('deleted_at'),
                ],
                'password' => 'required|string|min:8|confirmed',
                'role' => 'required|string|in:cliente,freelancer,empresa',
                'phone' => 'nullable|string|max:20',
                'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            if ($request->hasFile('profile_photo')) {
                $photoPath = $request->file('profile_photo')->store('profile_photos', 'public');
            }

            $user = DB::transaction(function () use ($validated, $photoPath) {
                $role = Role::where('name', $validated['role'])->first();

                if (! $role) {
                    throw new \Exception('El rol seleccionado no existe: ' . $validated['role']);
                }

                $user = User::create([
                    'name' => $validated['name'],
                    'last_name' => $validated['last_name'],
                    'maternal_last_name' => $validated['maternal_last_name'] ?? null,
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'phone' => $validated['phone'] ?? null,
                    'profile_photo' => $photoPath,
                    'is_active' => true,
                ]);

                $user->roles()->sync([
                    $role->id => [
                        'assigned_at' => now(),
                    ],
                ]);

                ActivityLoggerService::logRegister($user->id, $user->email);

                $user->load('roles');

                return $user;
            });

            return response()->json([
                'success' => true,
                'message' => 'Usuario registrado exitosamente',
                'data' => [
                    'user' => $this->formatUserResponse($user),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            if ($photoPath) {
                Storage::disk('public')->delete($photoPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al registrar usuario',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     operationId="me",
     *     tags={"Auth"},
     *     summary="Usuario autenticado",
     *     description="Obtiene los datos del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Datos del usuario autenticado"),
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function me()
    {
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
                'user' => $this->formatUserResponse($user),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/logout",
     *     operationId="logout",
     *     tags={"Auth"},
     *     summary="Cerrar sesión",
     *     description="Cierra la sesión del usuario autenticado",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Sesión cerrada exitosamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function logout()
    {
        $user = auth('api')->user();

        try {
            $guard = auth('api');
            $guard->logout();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo cerrar sesión',
                'error' => $e->getMessage(),
            ], 500);
        }

        if ($user) {
            ActivityLoggerService::logLogout($user->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/refresh",
     *     operationId="refresh",
     *     tags={"Auth"},
     *     summary="Refrescar token",
     *     description="Genera un nuevo token JWT",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Token refrescado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=500, description="Error interno del servidor")
     * )
     */
    public function refresh()
    {
        try {
            $guard = auth('api');
            $newToken = $guard->refresh();
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo refrescar el token',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token refrescado exitosamente',
            'data' => [
                'token' => $newToken,
            ],
        ]);
    }

    private function formatUserResponse(User $user): array
    {
        $role = $user->roles->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,

            // Ruta interna guardada en BD:
            // Ejemplo: profile_photos/abc123.jpg
            'profile_photo' => $user->profile_photo,

            // URL lista para usar en el frontend:
            // Ejemplo: http://127.0.0.1:8000/storage/profile_photos/abc123.jpg
            'profile_photo_url' => $user->profile_photo
                ? asset(Storage::url($user->profile_photo))
                : null,

            'is_active' => $user->is_active,
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ] : null,
        ];
    }
}
