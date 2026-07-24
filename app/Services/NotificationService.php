<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function create(
        int|User $user,
        string $type,
        string $message
    ): Notification {
        $userId = $user instanceof User
            ? $user->id
            : $user;

        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'is_read' => false,
        ]);
    }

    public static function message(
        int|User $receiver,
        string $senderName
    ): Notification {
        return self::create(
            $receiver,
            Notification::TYPE_MESSAGE,
            "{$senderName} te envió un mensaje."
        );
    }

    public static function applicationReceived(
        int|User $companyUser,
        string $freelancerName,
        string $vacancyTitle
    ): Notification {
        return self::create(
            $companyUser,
            Notification::TYPE_APPLICATION_RECEIVED,
            "{$freelancerName} se postuló a la vacante {$vacancyTitle}."
        );
    }

    public static function applicationStatus(
        int|User $freelancerUser,
        string $vacancyTitle,
        string $status
    ): Notification {
        $accepted = $status === 'accepted';

        return self::create(
            $freelancerUser,
            $accepted
                ? Notification::TYPE_APPLICATION_ACCEPTED
                : Notification::TYPE_APPLICATION_REJECTED,
            $accepted
                ? "Tu postulación a {$vacancyTitle} fue aceptada."
                : "Tu postulación a {$vacancyTitle} fue rechazada."
        );
    }

    public static function contractRequest(
        int|User $freelancerUser,
        string $clientName
    ): Notification {
        return self::create(
            $freelancerUser,
            Notification::TYPE_CONTRACT_REQUEST,
            "{$clientName} te envió una solicitud de contratación."
        );
    }

    public static function contractRequestStatus(
        int|User $user,
        string $status
    ): Notification {
        return match ($status) {
            'accepted' => self::create(
                $user,
                Notification::TYPE_CONTRACT_REQUEST_ACCEPTED,
                'Tu solicitud de contratación fue aceptada.'
            ),
            'rejected' => self::create(
                $user,
                Notification::TYPE_CONTRACT_REQUEST_REJECTED,
                'Tu solicitud de contratación fue rechazada.'
            ),
            'canceled' => self::create(
                $user,
                Notification::TYPE_CONTRACT_REQUEST_CANCELED,
                'Una solicitud de contratación fue cancelada.'
            ),
            default => throw new \InvalidArgumentException(
                'Estado de solicitud no compatible con notificaciones.'
            ),
        };
    }

    public static function contractStatus(
        int|User $user,
        string $status
    ): Notification {
        return match ($status) {
            'completed' => self::create(
                $user,
                Notification::TYPE_CONTRACT_COMPLETED,
                'Uno de tus contratos fue marcado como completado.'
            ),
            'canceled' => self::create(
                $user,
                Notification::TYPE_CONTRACT_CANCELED,
                'Uno de tus contratos fue cancelado.'
            ),
            default => self::create(
                $user,
                Notification::TYPE_CONTRACT_CREATED,
                'Se creó un nuevo contrato relacionado con tu cuenta.'
            ),
        };
    }

    public static function reviewReceived(
        int|User $evaluatedUser,
        int $rating
    ): Notification {
        return self::create(
            $evaluatedUser,
            Notification::TYPE_REVIEW_RECEIVED,
            "Recibiste una nueva calificación de {$rating} estrellas."
        );
    }
}