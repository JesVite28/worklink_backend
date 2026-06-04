<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Services\ActivityLoggerService;
use App\Models\User;

/**
 * @OA\Tag(
 *     name="Activity Logs",
 *     description="Endpoints para visualizar activity logs"
 * )
 */
class ActivityLogController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/activity-logs",
     *     tags={"Activity Logs"},
     *     summary="Obtener lista de activity logs",
     *     description="Retorna una lista paginada de activity logs con información del usuario asociado.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Registros por página",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="accion",
     *         in="query",
     *         description="Filtrar por acción",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="modulo",
     *         in="query",
     *         description="Filtrar por módulo",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="usuario_id",
     *         in="query",
     *         description="Filtrar por usuario",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lista de activity logs obtenida exitosamente",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lista de activity logs"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with('user');

        // Filtros opcionales
        if ($request->has('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }

        if ($request->has('accion')) {
            $query->where('accion', $request->accion);
        }

        if ($request->has('modulo')) {
            $query->where('modulo', $request->modulo);
        }

        if ($request->has('entidad')) {
            $query->where('entidad', $request->entidad);
        }

        $logs = $query->latest('creado_en')
            ->paginate($request->get('per_page', 20))
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'usuario_id' => $log->usuario_id,
                    'usuario' => $log->user ? [
                        'id' => $log->user->id,
                        'nombre' => $log->user->nombre,
                        'apellido' => $log->user->apellido,
                        'email' => $log->user->email,
                    ] : null,
                    'accion' => $log->accion,
                    'modulo' => $log->modulo,
                    'entidad' => $log->entidad,
                    'entidad_id' => $log->entidad_id,
                    'descripcion' => $log->descripcion,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'creado_en' => $log->creado_en,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Lista de activity logs',
            'data' => $logs
        ]);
    }

    /**
     * Obtener logs de un usuario específico.
     * 
     * NOTA: Método sin ruta registrada - deshabilitado para Swagger
     */
    /*
    public function userLogs($usuarioId, Request $request)
    {
        $logs = ActivityLog::where('usuario_id', $usuarioId)
            ->with('user')
            ->latest('creado_en')
            ->paginate($request->get('per_page', 20))
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'accion' => $log->accion,
                    'modulo' => $log->modulo,
                    'entidad' => $log->entidad,
                    'descripcion' => $log->descripcion,
                    'creado_en' => $log->creado_en,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Logs del usuario',
            'data' => $logs
        ]);
    }
    */

    /**
     * Obtener resumen de actividades por módulo.
     * 
     * @OA\Get(
     *     path="/api/activity-logs/summary",
     *     tags={"Activity Logs"},
     *     summary="Resumen de actividades",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Resumen de actividades obtenido exitosamente",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Resumen de actividades"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado. Token requerido o inválido"
     *     )
     * )
     */
    public function summary(Request $request)
    {
        $dias = $request->get('dias', 7);

        $resumen = [
            'total_acciones' => ActivityLog::whereBetween('creado_en', [
                now()->subDays($dias),
                now()
            ])->count(),
            'por_accion' => ActivityLog::whereBetween('creado_en', [
                now()->subDays($dias),
                now()
            ])->groupBy('accion')
                ->selectRaw('accion, count(*) as total')
                ->get()
                ->pluck('total', 'accion'),
            'por_modulo' => ActivityLog::whereBetween('creado_en', [
                now()->subDays($dias),
                now()
            ])->groupBy('modulo')
                ->selectRaw('modulo, count(*) as total')
                ->get()
                ->pluck('total', 'modulo'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Resumen de actividades',
            'data' => $resumen
        ]);
    }
}
