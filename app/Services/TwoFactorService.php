<?php

namespace App\Services;

use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class TwoFactorService
{
    public const EXPIRATION_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const TOKEN_LENGTH = 80;

    /**
     * Genera un desafío nuevo y envía
     * el código de verificación al correo.
     */
    public function createChallenge(
        User $user,
        string $purpose
    ): array {
        $this->validatePurpose($purpose);

        $plainCode = (string) random_int(
            100000,
            999999
        );

        $plainToken = Str::random(
            self::TOKEN_LENGTH
        );

        $challenge = DB::transaction(
            function () use (
                $user,
                $purpose,
                $plainCode,
                $plainToken
            ): TwoFactorChallenge {
                /*
                 * Invalida desafíos anteriores pendientes
                 * del mismo usuario y propósito.
                 */
                TwoFactorChallenge::query()
                    ->where(
                        'user_id',
                        $user->id
                    )
                    ->where(
                        'purpose',
                        $purpose
                    )
                    ->whereNull(
                        'verified_at'
                    )
                    ->delete();

                return TwoFactorChallenge::create([
                    'user_id' => $user->id,

                    'purpose' => $purpose,

                    /*
                     * El token público se guarda
                     * únicamente mediante SHA-256.
                     */
                    'token_hash' => hash(
                        'sha256',
                        $plainToken
                    ),

                    /*
                     * El código de seis dígitos
                     * tampoco se almacena directamente.
                     */
                    'code_hash' => Hash::make(
                        $plainCode
                    ),

                    'attempts' => 0,

                    'expires_at' => now()
                        ->addMinutes(
                            self::EXPIRATION_MINUTES
                        ),

                    'verified_at' => null,
                ]);
            }
        );

        /*
         * Envía el código al correo del usuario.
         */
        $user->notify(
            new TwoFactorCodeNotification(
                code: $plainCode,
                purpose: $purpose,
                expiresInMinutes:
                    self::EXPIRATION_MINUTES
            )
        );

        return [
            'challenge_token' =>
                $plainToken,

            'expires_in' =>
                self::EXPIRATION_MINUTES * 60,

            'expires_at' =>
                $challenge
                    ->expires_at
                    ->toIso8601String(),
        ];
    }

    /**
     * Valida un código de verificación.
     */
    public function verifyChallenge(
        string $plainToken,
        string $plainCode,
        string $purpose
    ): TwoFactorChallenge {
        $this->validatePurpose($purpose);

        $challenge = $this->findChallenge(
            $plainToken,
            $purpose
        );

        if ($challenge->isVerified()) {
            throw ValidationException::withMessages([
                'code' => [
                    'Este código ya fue utilizado.',
                ],
            ]);
        }

        if ($challenge->isExpired()) {
            throw ValidationException::withMessages([
                'code' => [
                    'El código de verificación ha expirado.',
                ],
            ]);
        }

        if (
            $challenge->hasExceededAttempts(
                self::MAX_ATTEMPTS
            )
        ) {
            throw ValidationException::withMessages([
                'code' => [
                    'Se alcanzó el límite de intentos. Solicita un código nuevo.',
                ],
            ]);
        }

        if (
            ! Hash::check(
                $plainCode,
                $challenge->code_hash
            )
        ) {
            $challenge->increment(
                'attempts'
            );

            $challenge->refresh();

            $remainingAttempts = max(
                0,
                self::MAX_ATTEMPTS -
                    $challenge->attempts
            );

            $message =
                $remainingAttempts > 0
                    ? "Código incorrecto. Te quedan {$remainingAttempts} intentos."
                    : 'Código incorrecto. Se alcanzó el límite de intentos.';

            throw ValidationException::withMessages([
                'code' => [
                    $message,
                ],
            ]);
        }

        $challenge->forceFill([
            'verified_at' => now(),
        ])->save();

        return $challenge->loadMissing(
            'user.roles'
        );
    }

    /**
     * Genera un código nuevo utilizando
     * un desafío existente.
     */
    public function resendChallenge(
        string $plainToken,
        string $purpose
    ): array {
        $this->validatePurpose($purpose);

        $challenge = $this->findChallenge(
            $plainToken,
            $purpose
        );

        if ($challenge->isVerified()) {
            throw ValidationException::withMessages([
                'challenge_token' => [
                    'Este proceso de verificación ya fue completado.',
                ],
            ]);
        }

        if ($challenge->isExpired()) {
            return $this->createChallenge(
                $challenge->user,
                $purpose
            );
        }

        return $this->createChallenge(
            $challenge->user,
            $purpose
        );
    }

    /**
     * Elimina un desafío después de completar
     * correctamente el proceso.
     */
    public function consumeChallenge(
        TwoFactorChallenge $challenge
    ): void {
        $challenge->delete();
    }

    /**
     * Obtiene un desafío por el token público
     * y el propósito correspondiente.
     */
    public function getChallenge(
        string $plainToken,
        string $purpose
    ): TwoFactorChallenge {
        $this->validatePurpose($purpose);

        return $this->findChallenge(
            $plainToken,
            $purpose
        );
    }

    /**
     * Busca un desafío por el hash del token.
     */
    private function findChallenge(
        string $plainToken,
        string $purpose
    ): TwoFactorChallenge {
        $tokenHash = hash(
            'sha256',
            $plainToken
        );

        $challenge = TwoFactorChallenge::query()
            ->with('user.roles')
            ->where(
                'token_hash',
                $tokenHash
            )
            ->where(
                'purpose',
                $purpose
            )
            ->first();

        if (! $challenge) {
            throw ValidationException::withMessages([
                'challenge_token' => [
                    'El proceso de verificación no es válido.',
                ],
            ]);
        }

        return $challenge;
    }

    /**
     * Evita utilizar propósitos arbitrarios.
     */
    private function validatePurpose(
        string $purpose
    ): void {
        $validPurposes = [
            TwoFactorChallenge::PURPOSE_LOGIN,

            TwoFactorChallenge::PURPOSE_ENABLE,

            TwoFactorChallenge::
                PURPOSE_CHANGE_PASSWORD,
        ];

        if (
            ! in_array(
                $purpose,
                $validPurposes,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Propósito 2FA no válido.'
            );
        }
    }
}