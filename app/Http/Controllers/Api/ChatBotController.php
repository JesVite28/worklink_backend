<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * @OA\Tag(
 *     name="Chatbot IA",
 *     description="Endpoints para el asistente inteligente de WorkLink"
 * )
 */
class ChatBotController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/chatbot/message",
     *     tags={"Chatbot IA"},
     *     summary="Enviar mensaje público al chatbot de WorkLink",
     *     description="Permite enviar un mensaje al asistente inteligente de WorkLink sin iniciar sesión. Responde dudas generales sobre la plataforma.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="¿Cómo funciona WorkLink?"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Respuesta generada correctamente"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al conectar con el servicio de IA"
     *     )
     * )
     */
    public function sendPublicMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $systemPrompt = $this->getPublicSystemPrompt();

        return $this->sendToOpenRouter(
            userMessage: $validated['message'],
            systemPrompt: $systemPrompt,
            mode: 'public'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/chatbot/auth-message",
     *     tags={"Chatbot IA"},
     *     summary="Enviar mensaje autenticado al chatbot de WorkLink",
     *     description="Permite enviar un mensaje al asistente inteligente de WorkLink usando el contexto del usuario autenticado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="¿Qué puedo hacer con mi tipo de cuenta?"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Respuesta generada correctamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Usuario no autenticado"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error al conectar con el servicio de IA"
     *     )
     * )
     */
    public function sendAuthMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $user = $request->user() ?? auth()->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $user->loadMissing('roles');

        $systemPrompt = $this->getAuthenticatedSystemPrompt($user);

        return $this->sendToOpenRouter(
            userMessage: $validated['message'],
            systemPrompt: $systemPrompt,
            mode: 'authenticated',
            user: $user
        );
    }

    /**
     * Compatibilidad temporal con la ruta anterior.
     * Si ya cambias api.php a sendPublicMessage, puedes borrar este método después.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        return $this->sendPublicMessage($request);
    }

    private function sendToOpenRouter(
        string $userMessage,
        string $systemPrompt,
        string $mode,
        ?User $user = null
    ): JsonResponse {
        $apiKey = config('services.openrouter.api_key');
        $model = config('services.openrouter.model');
        $siteUrl = config('services.openrouter.site_url');
        $appName = config('services.openrouter.app_name');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró la API Key de OpenRouter.',
            ], 500);
        }

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => $siteUrl,
                'X-Title' => $appName,
            ])
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $userMessage,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 700,
                'top_p' => 0.8,
            ]);

        if ($response->failed()) {
            return $this->handleOpenRouterError($response);
        }

        $reply = $response->json('choices.0.message.content');

        if (! $reply) {
            return response()->json([
                'success' => false,
                'message' => 'OpenRouter respondió, pero no se pudo obtener el contenido generado.',
                'error' => $response->json(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Respuesta generada correctamente.',
            'data' => [
                'provider' => 'openrouter',
                'model' => $model,
                'mode' => $mode,
                'user_id' => $user?->id,
                'reply' => $reply,
            ],
        ]);
    }

    private function handleOpenRouterError(Response $response): JsonResponse
    {
        if ($response->status() === 401) {
            return response()->json([
                'success' => false,
                'message' => 'API Key de OpenRouter inválida o no autorizada.',
                'error' => $response->json(),
            ], 401);
        }

        if ($response->status() === 402) {
            return response()->json([
                'success' => false,
                'message' => 'OpenRouter indica que no hay créditos disponibles o el modelo requiere pago.',
                'error' => $response->json(),
            ], 402);
        }

        if ($response->status() === 429) {
            return response()->json([
                'success' => false,
                'message' => 'Se alcanzó el límite de uso del modelo en OpenRouter. Intenta más tarde o cambia de modelo.',
                'error' => $response->json(),
            ], 429);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al conectar con OpenRouter.',
            'error' => $response->json(),
        ], 500);
    }

    private function getPublicSystemPrompt(): string
    {
        return <<<PROMPT
Eres LinkIA, el asistente inteligente público de WorkLink.

WorkLink es una plataforma que conecta clientes, freelancers y empresas locales.

Este chat es para usuarios que aún NO han iniciado sesión.

Tu trabajo es:
- Explicar qué es WorkLink.
- Ayudar a entender la diferencia entre cliente, freelancer y empresa.
- Orientar sobre cómo registrarse.
- Explicar de forma general cómo se publican servicios, vacantes o solicitudes.
- Ayudar a elegir qué tipo de cuenta conviene.
- Recomendar cómo describir una necesidad de trabajo.
- Responder dudas generales de la plataforma.

Reglas obligatorias:
- Responde únicamente en español latino.
- No mezcles idiomas.
- No uses caracteres chinos, japoneses, coreanos ni símbolos extraños.
- No inventes usuarios, perfiles, vacantes, servicios, precios reales ni datos de la base de datos.
- No afirmes que consultaste información real del sistema.
- No pidas datos sensibles.
- Sé claro, breve y útil.
- Usa listas cuando ayuden.
- Mantén un tono profesional, cercano y fácil de entender.
- Responde usando Markdown simple.
- Usa títulos cortos en negritas.
- Separa cada sección con saltos de línea.
- Usa listas con viñetas cuando expliques varios puntos.
- No escribas toda la respuesta en un solo párrafo.

Si el usuario pide consultar perfiles reales, solicitudes, contratos, vacantes o información de su cuenta, indícale que debe iniciar sesión para acceder a información personalizada.

Formato recomendado:
1. Respuesta directa.
2. Explicación breve.
3. Siguiente paso recomendado.
PROMPT;
    }

    private function getAuthenticatedSystemPrompt(User $user): string
    {
        $roles = $user->roles
            ->pluck('name')
            ->filter()
            ->values()
            ->implode(', ');

        $roles = $roles ?: 'sin rol asignado';

        return <<<PROMPT
Eres LinkIA, el asistente inteligente autenticado de WorkLink.

WorkLink es una plataforma que conecta clientes, freelancers y empresas locales.

El usuario ya inició sesión.

Contexto del usuario:
- ID: {$user->id}
- Nombre: {$user->name} {$user->last_name}
- Email: {$user->email}
- Roles: {$roles}
- Cuenta activa: {$user->is_active}

Tu trabajo es:
- Dar orientación personalizada según el rol del usuario.
- Explicar qué puede hacer dentro de WorkLink.
- Ayudar a entender los módulos disponibles.
- Orientar sobre perfiles, servicios, portafolio, disponibilidad, solicitudes, contratos y vacantes.
- Ayudar a redactar solicitudes, servicios o descripciones profesionales.
- Para usuarios admin, orientar sobre usuarios, roles, reportes, activity logs y gestión de plataforma.

Reglas obligatorias:
- Responde únicamente en español latino.
- No mezcles idiomas.
- No uses caracteres chinos, japoneses, coreanos ni símbolos extraños.
- No inventes datos reales del sistema.
- No digas que consultaste la base de datos si no se te proporcionaron datos concretos.
- No prometas crear, editar, eliminar o consultar registros directamente desde el chat.
- Si el usuario pide una acción real, indícale qué módulo debe usar o qué información necesitaría el sistema.
- Sé claro, breve y útil.
- Mantén un tono profesional, cercano y fácil de entender.
- Responde usando Markdown simple.
- Usa títulos cortos en negritas.
- Separa cada sección con saltos de línea.
- Usa listas con viñetas cuando expliques varios puntos.
- No escribas toda la respuesta en un solo párrafo.

Guía por rol:
- cliente: puede buscar freelancers, solicitar servicios y dar seguimiento a contrataciones.
- freelancer: puede completar perfil, publicar servicios, agregar portafolio y configurar disponibilidad.
- empresa: puede gestionar oportunidades, revisar candidatos y contratar talento.
- admin: puede gestionar usuarios, roles, reportes, logs y supervisar actividad del sistema.

Formato recomendado:
1. Respuesta directa.
2. Qué puedes hacer en tu cuenta.
3. Siguiente paso recomendado.
PROMPT;
    }
}