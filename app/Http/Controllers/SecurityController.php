<?php

namespace App\Http\Controllers;

use App\Models\TwoFactorChallenge;
use App\Services\ActivityLoggerService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * @OA\Tag(
 *     name="Security",
 *     description="Seguridad de la cuenta, verificación por correo y cambio de contraseña"
 * )
 */
class SecurityController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/security/password/code",
     *     operationId="sendPasswordChangeCode",
     *     summary="Solicitar código para cambiar la contraseña",
     *     description="Valida la contraseña actual del usuario autenticado y envía un código de seis dígitos a su correo electrónico. El código expira después de 10 minutos.",
     *     tags={"Security"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"current_password"},
     *
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 format="password",
     *                 example="password123",
     *                 description="Contraseña actual del usuario"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Código enviado correctamente",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Enviamos un código de verificación a tu correo electrónico."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="challenge_token",
     *                     type="string",
     *                     example="Y4P9x2Vt8cQ7kLmN..."
     *                 ),
     *                 @OA\Property(
     *                     property="expires_in",
     *                     type="integer",
     *                     example=600,
     *                     description="Tiempo de expiración en segundos"
     *                 ),
     *                 @OA\Property(
     *                     property="expires_at",
     *                     type="string",
     *                     format="date-time",
     *                     example="2026-07-25T23:30:00-06:00"
     *                 ),
     *                 @OA\Property(
     *                     property="email_hint",
     *                     type="string",
     *                     example="ad***@gmail.com"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No autorizado."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Contraseña actual incorrecta o datos inválidos",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="La contraseña actual es incorrecta."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="current_password",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="La contraseña actual no coincide con tu cuenta."
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function sendPasswordChangeCode(
        Request $request
    ): JsonResponse {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
        ]);

        if (
            ! Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'La contraseña actual es incorrecta.',

                'errors' => [
                    'current_password' => [
                        'La contraseña actual no coincide con tu cuenta.',
                    ],
                ],
            ], 422);
        }

        $challenge = $this->twoFactorService
            ->createChallenge(
                $user,
                TwoFactorChallenge::PURPOSE_CHANGE_PASSWORD
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Enviamos un código de verificación a tu correo electrónico.',

            'data' => [
                ...$challenge,

                'email_hint' =>
                    $this->maskEmail(
                        $user->email
                    ),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/security/password/change",
     *     operationId="changePasswordWithEmailVerification",
     *     summary="Cambiar contraseña utilizando código de correo",
     *     description="Verifica la contraseña actual, el desafío temporal y el código de seis dígitos enviado al correo. Después actualiza la contraseña e invalida la sesión actual.",
     *     tags={"Security"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={
     *                 "challenge_token",
     *                 "code",
     *                 "current_password",
     *                 "password",
     *                 "password_confirmation"
     *             },
     *
     *             @OA\Property(
     *                 property="challenge_token",
     *                 type="string",
     *                 example="Y4P9x2Vt8cQ7kLmN...",
     *                 description="Token temporal recibido al solicitar el código"
     *             ),
     *             @OA\Property(
     *                 property="code",
     *                 type="string",
     *                 example="482917",
     *                 minLength=6,
     *                 maxLength=6,
     *                 description="Código de seis dígitos enviado al correo"
     *             ),
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 format="password",
     *                 example="password123"
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
     *
     *     @OA\Response(
     *         response=200,
     *         description="Contraseña actualizada correctamente",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Tu contraseña fue actualizada correctamente. Inicia sesión nuevamente."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="session_terminated",
     *                     type="boolean",
     *                     example=true
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No autorizado."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="El desafío no pertenece al usuario autenticado",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="El proceso de verificación no pertenece a tu cuenta."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Código incorrecto, expirado, contraseña actual incorrecta o datos inválidos",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="The given data was invalid."
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="string",
     *                         example="Código incorrecto. Te quedan 4 intentos."
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function changePassword(
        Request $request
    ): JsonResponse {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $validated = $request->validate([
            'challenge_token' => [
                'required',
                'string',
            ],

            'code' => [
                'required',
                'string',
                'digits:6',
            ],

            'current_password' => [
                'required',
                'string',
            ],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        /*
         * Se valida nuevamente la contraseña actual,
         * porque pudo cambiar después de enviar el código.
         */
        if (
            ! Hash::check(
                $validated['current_password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'La contraseña actual es incorrecta.',

                'errors' => [
                    'current_password' => [
                        'La contraseña actual no coincide con tu cuenta.',
                    ],
                ],
            ], 422);
        }

        if (
            Hash::check(
                $validated['password'],
                $user->password
            )
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'La nueva contraseña debe ser diferente a la actual.',

                'errors' => [
                    'password' => [
                        'La nueva contraseña debe ser diferente a la actual.',
                    ],
                ],
            ], 422);
        }

        /*
         * Se comprueba primero que el desafío
         * corresponda al usuario autenticado.
         */
        $challenge = $this->twoFactorService
            ->getChallenge(
                $validated['challenge_token'],
                TwoFactorChallenge::PURPOSE_CHANGE_PASSWORD
            );

        if (
            (int) $challenge->user_id
            !== (int) $user->id
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'El proceso de verificación no pertenece a tu cuenta.',

                'errors' => [
                    'challenge_token' => [
                        'El proceso de verificación no es válido.',
                    ],
                ],
            ], 403);
        }

        /*
         * Se comprueban el código, expiración,
         * intentos y propósito del desafío.
         */
        $verifiedChallenge = $this->twoFactorService
            ->verifyChallenge(
                $validated['challenge_token'],
                $validated['code'],
                TwoFactorChallenge::PURPOSE_CHANGE_PASSWORD
            );

        DB::transaction(
            function () use (
                $user,
                $validated,
                $verifiedChallenge
            ): void {
                $user->update([
                    'password' => Hash::make(
                        $validated['password']
                    ),
                ]);

                ActivityLoggerService::logUpdate(
                    module: 'SECURITY',
                    entity: 'users',
                    entityId: $user->id,
                    description:
                        "User {$user->name} {$user->last_name} changed their password using email verification"
                );

                /*
                 * El desafío se elimina después
                 * de completar el cambio.
                 */
                $this->twoFactorService
                    ->consumeChallenge(
                        $verifiedChallenge
                    );
            }
        );

        /*
         * Invalida el JWT utilizado
         * durante el cambio de contraseña.
         */
        try {
            auth('api')->logout();
        } catch (\Throwable $exception) {
            /*
             * La contraseña ya fue actualizada.
             * No se interrumpe la respuesta si el token
             * no puede invalidarse.
             */
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Tu contraseña fue actualizada correctamente. Inicia sesión nuevamente.',

            'data' => [
                'session_terminated' => true,
            ],
        ]);
    }

    /**
     * Oculta parcialmente el correo.
     *
     * Ejemplo:
     * adrian@gmail.com → ad***@gmail.com
     */
    private function maskEmail(
        string $email
    ): string {
        [
            $localPart,
            $domain,
        ] = array_pad(
            explode(
                '@',
                $email,
                2
            ),
            2,
            ''
        );

        if ($domain === '') {
            return $email;
        }

        $visibleCharacters = min(
            2,
            strlen($localPart)
        );

        $visiblePart = substr(
            $localPart,
            0,
            $visibleCharacters
        );

        return $visiblePart .
            '***@' .
            $domain;
    }
}