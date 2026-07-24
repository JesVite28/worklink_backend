<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Services\ActivityLoggerService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Messages",
 *     description="Endpoints para mensajería entre usuarios"
 * )
 */
class MessageController extends Controller
{
    private function publicUrl(?string $path): ?string
    {
        if (! $path) return null;
        if (filter_var($path, FILTER_VALIDATE_URL)) return $path;

        return asset(Storage::url($path));
    }

    private function formatUser(?User $user): ?array
    {
        if (! $user) return null;

        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->publicUrl($user->profile_photo),
            'is_active' => $user->is_active,
            'role' => $user->roles->first()?->name,
        ];
    }

    private function formatMessage(Message $message): array
    {
        $message->loadMissing(['sender.roles', 'receiver.roles']);

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'sender' => $this->formatUser($message->sender),
            'receiver_id' => $message->receiver_id,
            'receiver' => $this->formatUser($message->receiver),
            'content' => $message->content,
            'is_read' => $message->is_read,
            'created_at' => $message->created_at,
            'updated_at' => $message->updated_at,
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/messages/conversations",
     *     operationId="listMessageConversations",
     *     tags={"Messages"},
     *     summary="Listar conversaciones del usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Conversaciones obtenidas exitosamente")
     * )
     */
    public function conversations()
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $messages = Message::with(['sender.roles', 'receiver.roles'])
            ->where(fn ($q) => $q
                ->where('sender_id', $user->id)
                ->orWhere('receiver_id', $user->id))
            ->latest('created_at')
            ->get();

        $conversations = $messages
            ->groupBy(fn (Message $message) =>
                $message->sender_id === $user->id
                    ? $message->receiver_id
                    : $message->sender_id
            )
            ->map(function ($group, $otherUserId) use ($user) {
                $last = $group->first();
                $other = $last->sender_id === $user->id
                    ? $last->receiver
                    : $last->sender;

                return [
                    'user' => $this->formatUser($other),
                    'last_message' => $this->formatMessage($last),
                    'unread_count' => $group
                        ->where('receiver_id', $user->id)
                        ->where('is_read', false)
                        ->count(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Conversaciones obtenidas exitosamente',
            'data' => ['conversations' => $conversations],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/messages/conversation/{userId}",
     *     operationId="showMessageConversation",
     *     tags={"Messages"},
     *     summary="Obtener conversación con otro usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Conversación obtenida exitosamente"),
     *     @OA\Response(response=404, description="Usuario no encontrado")
     * )
     */
    public function conversation(Request $request, int $userId)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $other = User::with('roles')->find($userId);

        if (! $other) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado.'], 404);
        }

        $perPage = min(max((int) $request->input('per_page', 30), 1), 100);

        $paginator = Message::with(['sender.roles', 'receiver.roles'])
            ->where(function ($q) use ($user, $other) {
                $q->where(function ($sub) use ($user, $other) {
                    $sub->where('sender_id', $user->id)
                        ->where('receiver_id', $other->id);
                })->orWhere(function ($sub) use ($user, $other) {
                    $sub->where('sender_id', $other->id)
                        ->where('receiver_id', $user->id);
                });
            })
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Conversación obtenida exitosamente',
            'data' => [
                'user' => $this->formatUser($other),
                'messages' => collect($paginator->items())
                    ->map(fn (Message $message) => $this->formatMessage($message))
                    ->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/messages",
     *     operationId="sendMessage",
     *     tags={"Messages"},
     *     summary="Enviar mensaje",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"receiver_id","content"},
     *         @OA\Property(property="receiver_id", type="integer", example=2),
     *         @OA\Property(property="content", type="string", example="Hola, me interesa tu servicio.")
     *     )),
     *     @OA\Response(response=201, description="Mensaje enviado exitosamente"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function store(Request $request)
    {
        $sender = auth('api')->user();

        if (! $sender) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $validated = $request->validate([
            'receiver_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                Rule::notIn([$sender->id]),
            ],
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $message = DB::transaction(function () use ($validated, $sender) {
            $message = Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $validated['receiver_id'],
                'content' => $validated['content'],
                'is_read' => false,
            ]);

            ActivityLoggerService::logCreate(
                module: 'MESSAGES',
                entity: 'messages',
                entityId: $message->id,
                description: "Message sent to user ID {$message->receiver_id}"
            );

            $senderName = trim(
                $sender->name
                . ' '
                . $sender->last_name
            );

            NotificationService::message(
                $message->receiver_id,
                $senderName
            );

            return $message;
        });

        $message->load(['sender.roles', 'receiver.roles']);

        return response()->json([
            'success' => true,
            'message' => 'Mensaje enviado exitosamente',
            'data' => ['message' => $this->formatMessage($message)],
        ], 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/messages/{id}/read",
     *     operationId="markMessageAsRead",
     *     tags={"Messages"},
     *     summary="Marcar mensaje como leído",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mensaje marcado como leído")
     * )
     */
    public function markAsRead(int $id)
    {
        $user = auth('api')->user();
        $message = Message::find($id);

        if (! $message) {
            return response()->json(['success' => false, 'message' => 'Mensaje no encontrado.'], 404);
        }

        if (! $user || ($message->receiver_id !== $user->id && ! $user->hasRole('admin'))) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para marcar este mensaje.'], 403);
        }

        if (! $message->is_read) {
            DB::transaction(function () use ($message) {
                $message->update(['is_read' => true]);

                ActivityLoggerService::logUpdate(
                    module: 'MESSAGES',
                    entity: 'messages',
                    entityId: $message->id,
                    description: "Message ID {$message->id} marked as read"
                );
            });
        }

        $message->refresh()->load(['sender.roles', 'receiver.roles']);

        return response()->json([
            'success' => true,
            'message' => 'Mensaje marcado como leído',
            'data' => ['message' => $this->formatMessage($message)],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/messages/read-all/{userId}",
     *     operationId="markConversationAsRead",
     *     tags={"Messages"},
     *     summary="Marcar conversación como leída",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Conversación marcada como leída")
     * )
     */
    public function markConversationAsRead(int $userId)
    {
        $user = auth('api')->user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'No autorizado.'], 401);
        }

        $updated = Message::where('sender_id', $userId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Conversación marcada como leída',
            'data' => ['updated_messages' => $updated],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/messages/{id}",
     *     operationId="deleteMessage",
     *     tags={"Messages"},
     *     summary="Eliminar mensaje no leído",
     *     description="El emisor puede retirar un mensaje mientras no haya sido leído. El administrador puede eliminar cualquiera.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Mensaje eliminado correctamente")
     * )
     */
    public function destroy(int $id)
    {
        $user = auth('api')->user();
        $message = Message::find($id);

        if (! $message) {
            return response()->json(['success' => false, 'message' => 'Mensaje no encontrado.'], 404);
        }

        $isAdmin = $user && $user->hasRole('admin');
        $isSender = $user && $message->sender_id === $user->id;

        if (! $isAdmin && ! $isSender) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para eliminar este mensaje.'], 403);
        }

        if (! $isAdmin && $message->is_read) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes retirar un mensaje que ya fue leído.',
            ], 409);
        }

        DB::transaction(function () use ($message) {
            ActivityLoggerService::logDelete(
                module: 'MESSAGES',
                entity: 'messages',
                entityId: $message->id,
                description: "Message ID {$message->id} deleted"
            );

            $message->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Mensaje eliminado correctamente.',
        ]);
    }
}