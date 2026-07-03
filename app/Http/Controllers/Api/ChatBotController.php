<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     *     summary="Enviar mensaje al chatbot de WorkLink",
     *     description="Permite enviar un mensaje al asistente inteligente de WorkLink para recibir asesoría sobre freelancers, empresas, servicios o solicitudes.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message"},
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Necesito un freelancer que me haga una página web para mi restaurante"
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
    public function sendMessage(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $userMessage = $request->message;

        $systemPrompt = "
        Eres LinkIA, el asistente inteligente de WorkLink.

        WorkLink es una plataforma que conecta clientes, freelancers y empresas locales.

        Tu trabajo es:
        - Ayudar a clientes a encontrar freelancers o empresas según su necesidad.
        - Asesorar sobre contratación, servicios, habilidades y presupuesto.
        - Ayudar a redactar solicitudes de trabajo.
        - Orientar a freelancers para mejorar su perfil.
        - Recomendar qué tipo de perfil buscar, sin inventar usuarios reales.

        Reglas obligatorias:
        - Responde únicamente en español latino.
        - No mezcles idiomas.
        - No uses caracteres chinos, japoneses, coreanos ni símbolos extraños.
        - No inventes palabras.
        - No des información falsa.
        - Sé claro, breve y útil.
        - Usa listas ordenadas cuando ayude.
        - Si el usuario pide buscar perfiles reales, aclara que primero necesitas consultar la base de datos.
        - Mantén un tono profesional, cercano y fácil de entender.

        Formato recomendado:
        1. Recomendación principal.
        2. Perfil ideal.
        3. Habilidades necesarias.
        4. Presupuesto o consejos si aplica.
        5. Siguiente paso recomendado.
        ";
        $apiKey = config('services.openrouter.api_key');
        $model = config('services.openrouter.model');
        $siteUrl = config('services.openrouter.site_url');
        $appName = config('services.openrouter.app_name');

        if (!$apiKey) {
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
                'message' => 'Se alcanzó el límite de uso del modelo gratuito en OpenRouter. Intenta más tarde o cambia de modelo.',
                'error' => $response->json(),
            ], 429);
        }

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Error al conectar con OpenRouter.',
                'error' => $response->json(),
            ], 500);
        }

        $reply = $response->json('choices.0.message.content');

        if (!$reply) {
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
                'reply' => $reply,
            ],
        ]);
    }
}
