<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Notifications",
 *     description="Gestión de notificaciones del usuario autenticado"
 * )
 */
class NotificationController extends Controller
{
    private function formatNotification(
        Notification $notification
    ): array {
        return [
            'id' => $notification->id,
            'user_id' => $notification->user_id,
            'type' => $notification->type,
            'message' => $notification->message,
            'is_read' => $notification->is_read,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     operationId="listNotifications",
     *     tags={"Notifications"},
     *     summary="Listar notificaciones propias",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Notificaciones obtenidas")
     * )
     */
    public function index(Request $request)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $request->validate([
            'is_read' => [
                'nullable',
                Rule::in(['0', '1', 0, 1, true, false]),
            ],
            'type' => [
                'nullable',
                'string',
                Rule::in(Notification::TYPES),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Notification::query()
            ->where('user_id', $user->id);

        if ($request->has('is_read')) {
            $query->where(
                'is_read',
                $request->boolean('is_read')
            );
        }

        if ($request->filled('type')) {
            $query->where(
                'type',
                $request->input('type')
            );
        }

        $paginator = $query
            ->latest('created_at')
            ->paginate(
                (int) $request->input(
                    'per_page',
                    20
                )
            );

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones obtenidas exitosamente',
            'data' => [
                'notifications' => collect(
                    $paginator->items()
                )->map(
                    fn (Notification $notification) =>
                        $this->formatNotification(
                            $notification
                        )
                )->values(),
                'unread_count' => Notification::query()
                    ->where('user_id', $user->id)
                    ->where('is_read', false)
                    ->count(),
                'pagination' => [
                    'current_page' =>
                        $paginator->currentPage(),
                    'last_page' =>
                        $paginator->lastPage(),
                    'per_page' =>
                        $paginator->perPage(),
                    'total' =>
                        $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/unread-count",
     *     operationId="notificationUnreadCount",
     *     tags={"Notifications"},
     *     summary="Obtener cantidad de notificaciones no leídas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Cantidad obtenida")
     * )
     */
    public function unreadCount()
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cantidad obtenida exitosamente',
            'data' => [
                'unread_count' =>
                    Notification::query()
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->where(
                            'is_read',
                            false
                        )
                        ->count(),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/notifications/{id}/read",
     *     operationId="markNotificationAsRead",
     *     tags={"Notifications"},
     *     summary="Marcar una notificación como leída",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notificación actualizada")
     * )
     */
    public function markAsRead(int $id)
    {
        $user = auth('api')->user();

        $notification = Notification::query()
            ->where('user_id', $user?->id)
            ->find($id);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada.',
            ], 404);
        }

        if (! $notification->is_read) {
            DB::transaction(function () use (
                $notification
            ) {
                $notification->update([
                    'is_read' => true,
                ]);

                ActivityLoggerService::logUpdate(
                    module: 'NOTIFICATIONS',
                    entity: 'notifications',
                    entityId: $notification->id,
                    description: "Notification ID {$notification->id} marked as read"
                );
            });
        }

        $notification->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Notificación marcada como leída',
            'data' => [
                'notification' =>
                    $this->formatNotification(
                        $notification
                    ),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/notifications/read-all",
     *     operationId="markAllNotificationsAsRead",
     *     tags={"Notifications"},
     *     summary="Marcar todas las notificaciones como leídas",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Notificaciones actualizadas")
     * )
     */
    public function markAllAsRead()
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $updated = Notification::query()
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notificaciones marcadas como leídas',
            'data' => [
                'updated_notifications' => $updated,
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{id}",
     *     operationId="deleteNotification",
     *     tags={"Notifications"},
     *     summary="Eliminar una notificación propia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Notificación eliminada")
     * )
     */
    public function destroy(int $id)
    {
        $user = auth('api')->user();

        $notification = Notification::query()
            ->where('user_id', $user?->id)
            ->find($id);

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notificación no encontrada.',
            ], 404);
        }

        DB::transaction(function () use (
            $notification
        ) {
            ActivityLoggerService::logDelete(
                module: 'NOTIFICATIONS',
                entity: 'notifications',
                entityId: $notification->id,
                description: "Notification ID {$notification->id} deleted"
            );

            $notification->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Notificación eliminada exitosamente.',
        ]);
    }
}