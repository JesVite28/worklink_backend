<?php

namespace App\Notifications;

use App\Models\TwoFactorChallenge;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        private readonly string $code,
        private readonly string $purpose,
        private readonly int $expiresInMinutes = 10
    ) {
        $this->afterCommit();
    }

    /**
     * Canales de envío.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Correo de verificación.
     */
    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $title, $description] = match ($this->purpose) {
            TwoFactorChallenge::PURPOSE_ENABLE => [
                'Confirma la activación del 2FA en WorkLink',
                'Activa tu verificación en dos pasos',
                'Utiliza el siguiente código para confirmar la activación de la verificación en dos pasos de tu cuenta.',
            ],

            TwoFactorChallenge::PURPOSE_CHANGE_PASSWORD => [
                'Código para cambiar tu contraseña en WorkLink',
                'Cambio de contraseña',
                'Recibimos una solicitud para cambiar la contraseña de tu cuenta. Utiliza el siguiente código para continuar.',
            ],

            TwoFactorChallenge::PURPOSE_LOGIN => [
                'Código de inicio de sesión de WorkLink',
                'Verifica tu inicio de sesión',
                'Utiliza el siguiente código para completar el inicio de sesión en tu cuenta de WorkLink.',
            ],

            default => [
                'Código de verificación de WorkLink',
                'Verifica tu identidad',
                'Utiliza el siguiente código para continuar con la verificación de tu cuenta.',
            ],
        };

        $name = trim((string) ($notifiable->name ?? ''));

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.two-factor-code', [
                'name' => $name !== '' ? $name : 'usuario',
                'title' => $title,
                'description' => $description,
                'code' => $this->code,
                'expirationMinutes' => $this->expiresInMinutes,
            ]);
    }
}