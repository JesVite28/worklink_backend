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
        /*
         * Evita que la notificación se procese antes
         * de finalizar una transacción de base de datos.
         */
        $this->afterCommit();
    }

    /**
     * Canales por los que se enviará la notificación.
     */
    public function via(object $notifiable): array
    {
        return [
            'mail',
        ];
    }

    /**
     * Contenido del correo.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->getMailContent();

        return (new MailMessage)
            ->subject(
                $content['subject']
            )
            ->greeting(
                'Hola, ' .
                ($notifiable->name ?? 'usuario')
            )
            ->line(
                $content['description']
            )
            ->line(
                'Utiliza el siguiente código de verificación:'
            )
            ->line(
                $this->code
            )
            ->line(
                "Este código expirará en {$this->expiresInMinutes} minutos."
            )
            ->line(
                'Nunca compartas este código con otra persona.'
            )
            ->line(
                'Si tú no realizaste esta solicitud, ignora este correo y considera cambiar tu contraseña.'
            )
            ->salutation(
                'Equipo de WorkLink'
            );
    }

    /**
     * Define el contenido de acuerdo
     * con el propósito del código.
     */
    private function getMailContent(): array
    {
        return match ($this->purpose) {
            TwoFactorChallenge::PURPOSE_ENABLE => [
                'subject' =>
                    'Confirma la activación del 2FA en WorkLink',

                'description' =>
                    'Recibimos una solicitud para activar la verificación en dos pasos de tu cuenta de WorkLink.',
            ],

            TwoFactorChallenge::PURPOSE_CHANGE_PASSWORD => [
                'subject' =>
                    'Código para cambiar tu contraseña en WorkLink',

                'description' =>
                    'Recibimos una solicitud para cambiar la contraseña de tu cuenta de WorkLink.',
            ],

            TwoFactorChallenge::PURPOSE_LOGIN => [
                'subject' =>
                    'Código de inicio de sesión de WorkLink',

                'description' =>
                    'Se intentó iniciar sesión en tu cuenta de WorkLink.',
            ],

            default => [
                'subject' =>
                    'Código de verificación de WorkLink',

                'description' =>
                    'Recibimos una solicitud de verificación para tu cuenta de WorkLink.',
            ],
        };
    }

    /**
     * Datos opcionales de la notificación.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'purpose' =>
                $this->purpose,

            'expires_in_minutes' =>
                $this->expiresInMinutes,
        ];
    }
}