<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\ActivityLoggerService;
use App\Services\TwoFactorService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
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
    public function login(
        Request $request,
        TwoFactorService $twoFactorService
    ) {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:150',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $user = User::query()
            ->with('roles')
            ->where('email', $validated['email'])
            ->whereNull('deleted_at')
            ->first();

        if (
            ! $user ||
            ! Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Tu cuenta se encuentra desactivada.',
            ], 403);
        }

        if (
            Hash::needsRehash($user->password)
        ) {
            $user->forceFill([
                'password' => Hash::make(
                    $validated['password']
                ),
            ])->save();
        }

        if ($user->two_factor_enabled) {
            try {
                $challenge =
                    $twoFactorService->createChallenge(
                        $user,
                        TwoFactorChallenge::PURPOSE_LOGIN
                    );
            } catch (\Throwable $exception) {
                Log::error(
                    'No fue posible crear el desafío 2FA.',
                    [
                        'user_id' => $user->id,
                        'exception' => $exception,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => 'No fue posible enviar el código de verificación.',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'message' => 'Enviamos un código de verificación a tu correo.',
                'data' => [
                    ...$challenge,
                    'email_hint' =>
                        $this->maskEmail($user->email),
                ],
            ]);
        }

        try {
            $token = auth('api')->login($user);
        } catch (JWTException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el token.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        ActivityLoggerService::logLogin($user->id);

        return response()->json([
            'success' => true,
            'requires_2fa' => false,
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
     * @OA\Post(
     *     path="/api/forgot-password",
     *     operationId="forgotPassword",
     *     tags={"Auth"},
     *     summary="Solicitar recuperación de contraseña",
     *     description="Envía un enlace de recuperación al correo indicado cuando existe una cuenta asociada.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="usuario@worklink.com"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Solicitud procesada"),
     *     @OA\Response(response=422, description="Correo inválido"),
     *     @OA\Response(response=429, description="Demasiadas solicitudes"),
     *     @OA\Response(response=500, description="No se pudo enviar el correo")
     * )
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:150',
            ],
        ]);

        try {
            $status = Password::sendResetLink([
                'email' => $validated['email'],
            ]);
        } catch (\Throwable $exception) {
            Log::error(
                'No fue posible enviar el correo de recuperación.',
                [
                    'email' => $validated['email'],
                    'exception' => $exception,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible enviar el correo de recuperación. Intenta nuevamente.',
            ], 500);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'success' => false,
                'message' => 'Espera un momento antes de solicitar otro enlace.',
            ], 429);
        }

        /*
         * Se devuelve el mismo mensaje aunque el correo no exista.
         * Esto evita revelar qué cuentas están registradas.
         */
        return response()->json([
            'success' => true,
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reset-password",
     *     operationId="resetPassword",
     *     tags={"Auth"},
     *     summary="Restablecer contraseña",
     *     description="Actualiza la contraseña utilizando un token de recuperación válido.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","email","password","password_confirmation"},
     *             @OA\Property(
     *                 property="token",
     *                 type="string",
     *                 example="token-recibido-por-correo"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="usuario@worklink.com"
     *             ),
     *             @OA\Property(
     *                 property="password",
     *                 type="string",
     *                 format="password",
     *                 minLength=8,
     *                 example="NuevaPassword123"
     *             ),
     *             @OA\Property(
     *                 property="password_confirmation",
     *                 type="string",
     *                 format="password",
     *                 example="NuevaPassword123"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contraseña restablecida"),
     *     @OA\Response(response=422, description="Token o datos inválidos"),
     *     @OA\Response(response=500, description="No se pudo restablecer la contraseña")
     * )
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'email',
                'max:150',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        try {
            $status = Password::reset(
                [
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'password_confirmation' => $request->input(
                        'password_confirmation'
                    ),
                    'token' => $validated['token'],
                ],
                function (
                    User $user,
                    string $password
                ): void {
                    $user->forceFill([
                        'password' => Hash::make($password),
                    ])->save();

                    event(new PasswordReset($user));
                }
            );
        } catch (\Throwable $exception) {
            Log::error(
                'No fue posible restablecer la contraseña.',
                [
                    'email' => $validated['email'],
                    'exception' => $exception,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible restablecer la contraseña. Intenta nuevamente.',
            ], 500);
        }

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => match ($status) {
                    Password::INVALID_TOKEN => 'El enlace de recuperación es inválido o expiró.',
                    Password::INVALID_USER => 'No fue posible validar la cuenta indicada.',
                    Password::RESET_THROTTLED => 'Espera un momento antes de intentarlo nuevamente.',
                    default => 'No fue posible restablecer la contraseña.',
                },
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contraseña restablecida exitosamente.',
        ]);
    }

    /**
     * Verifica el código 2FA del inicio de sesión y genera el JWT.
     */
    public function verifyTwoFactorLogin(
        Request $request,
        TwoFactorService $twoFactorService
    ) {
        $validated = $request->validate([
            'challenge_token' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        $challenge =
            $twoFactorService->verifyChallenge(
                $validated['challenge_token'],
                $validated['code'],
                TwoFactorChallenge::PURPOSE_LOGIN
            );

        $user = $challenge->user;

        if (! $user || ! $user->is_active) {
            $twoFactorService->consumeChallenge(
                $challenge
            );

            return response()->json([
                'success' => false,
                'message' => 'La cuenta no está disponible.',
            ], 403);
        }

        try {
            $token = auth('api')->login($user);
        } catch (JWTException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo crear el token.',
                'error' => $exception->getMessage(),
            ], 500);
        }

        ActivityLoggerService::logLogin($user->id);

        $twoFactorService->consumeChallenge(
            $challenge
        );

        return response()->json([
            'success' => true,
            'requires_2fa' => false,
            'message' => 'Código verificado correctamente.',
            'data' => [
                'token' => $token,
                'user' => $this->formatUserResponse(
                    $user->loadMissing('roles')
                ),
            ],
        ]);
    }

    /**
     * Reenvía el código 2FA para un inicio de sesión pendiente.
     */
    public function resendTwoFactorLogin(
        Request $request,
        TwoFactorService $twoFactorService
    ) {
        $validated = $request->validate([
            'challenge_token' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $challenge =
            $twoFactorService->resendChallenge(
                $validated['challenge_token'],
                TwoFactorChallenge::PURPOSE_LOGIN
            );

        return response()->json([
            'success' => true,
            'requires_2fa' => true,
            'message' => 'Enviamos un código nuevo a tu correo.',
            'data' => $challenge,
        ]);
    }

    /**
     * Consulta si el usuario autenticado tiene 2FA activo.
     */
    public function twoFactorStatus()
    {
        /** @var User|null $user */
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Estado de verificación en dos pasos.',
            'data' => [
                'enabled' =>
                    (bool) $user->two_factor_enabled,
                'enabled_at' =>
                    $user->two_factor_enabled_at
                        ?->toIso8601String(),
                'email_hint' =>
                    $this->maskEmail($user->email),
            ],
        ]);
    }

    /**
     * Solicita el código necesario para activar el 2FA.
     */
    public function requestTwoFactorEnable(
        Request $request,
        TwoFactorService $twoFactorService
    ) {
        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
        ]);

        /** @var User|null $user */
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        if (
            ! Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'La contraseña actual es incorrecta.',
                ],
            ]);
        }

        if ($user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'La verificación en dos pasos ya está activada.',
            ], 409);
        }

        $challenge =
            $twoFactorService->createChallenge(
                $user,
                TwoFactorChallenge::PURPOSE_ENABLE
            );

        return response()->json([
            'success' => true,
            'message' => 'Enviamos un código para confirmar la activación.',
            'data' => [
                ...$challenge,
                'email_hint' =>
                    $this->maskEmail($user->email),
            ],
        ]);
    }

    /**
     * Confirma el código y activa el 2FA.
     */
    public function verifyTwoFactorEnable(
        Request $request,
        TwoFactorService $twoFactorService
    ) {
        $validated = $request->validate([
            'challenge_token' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'digits:6',
            ],
        ]);

        /** @var User|null $authenticatedUser */
        $authenticatedUser = auth('api')->user();

        if (! $authenticatedUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        $challenge =
            $twoFactorService->verifyChallenge(
                $validated['challenge_token'],
                $validated['code'],
                TwoFactorChallenge::PURPOSE_ENABLE
            );

        if (
            (int) $challenge->user_id !==
            (int) $authenticatedUser->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'El desafío no pertenece al usuario autenticado.',
            ], 403);
        }

        DB::transaction(
            function () use (
                $authenticatedUser,
                $challenge,
                $twoFactorService
            ): void {
                $authenticatedUser->forceFill([
                    'two_factor_enabled' => true,
                    'two_factor_enabled_at' => now(),
                ])->save();

                $twoFactorService->consumeChallenge(
                    $challenge
                );
            }
        );

        return response()->json([
            'success' => true,
            'message' => 'Verificación en dos pasos activada correctamente.',
            'data' => [
                'enabled' => true,
                'enabled_at' =>
                    $authenticatedUser
                        ->fresh()
                        ?->two_factor_enabled_at
                        ?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Desactiva el 2FA después de validar la contraseña actual.
     */
    public function disableTwoFactor(
        Request $request
    ) {
        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
        ]);

        /** @var User|null $user */
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        }

        if (
            ! Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'La contraseña actual es incorrecta.',
                ],
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'two_factor_enabled' => false,
                'two_factor_enabled_at' => null,
            ])->save();

            $user->twoFactorChallenges()->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Verificación en dos pasos desactivada correctamente.',
            'data' => [
                'enabled' => false,
                'enabled_at' => null,
            ],
        ]);
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

    private function maskEmail(string $email): string
    {
        [$localPart, $domain] = array_pad(
            explode('@', $email, 2),
            2,
            ''
        );

        if ($domain === '') {
            return '***';
        }

        $visibleCharacters = min(
            2,
            mb_strlen($localPart)
        );

        $visiblePart = mb_substr(
            $localPart,
            0,
            $visibleCharacters
        );

        return $visiblePart . '***@' . $domain;
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
            'two_factor_enabled' =>
                (bool) $user->two_factor_enabled,
            'two_factor_enabled_at' =>
                $user->two_factor_enabled_at
                    ?->toIso8601String(),
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ] : null,
        ];
    }
}