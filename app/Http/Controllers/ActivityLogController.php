<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

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
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Registros por página",
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filtrar por ID del usuario",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="action",
     *         in="query",
     *         description="Filtrar por acción",
     *         @OA\Schema(
     *             type="string",
     *             enum={"LOGIN","LOGOUT","REGISTER","CREATE","UPDATE","DELETE","EXPORT","IMPORT","DOWNLOAD","VIEW"},
     *             example="LOGIN"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="module",
     *         in="query",
     *         description="Filtrar por módulo",
     *         @OA\Schema(type="string", example="AUTHENTICATION")
     *     ),
     *     @OA\Parameter(
     *         name="entity",
     *         in="query",
     *         description="Filtrar por entidad",
     *         @OA\Schema(type="string", example="users")
     *     ),
     *
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'action' => 'nullable|string|in:LOGIN,LOGOUT,REGISTER,CREATE,UPDATE,DELETE,EXPORT,IMPORT,DOWNLOAD,VIEW',
            'module' => 'nullable|string|max:80',
            'entity' => 'nullable|string|max:80',
        ]);

        $query = ActivityLog::with('user');

        if ($request->filled('user_id')) {
            $query->where('user_id', $validated['user_id']);
        }

        if ($request->filled('action')) {
            $query->where('action', $validated['action']);
        }

        if ($request->filled('module')) {
            $query->where('module', $validated['module']);
        }

        if ($request->filled('entity')) {
            $query->where('entity', $validated['entity']);
        }

        $logs = $query->latest('created_at')
            ->paginate($validated['per_page'] ?? 20)
            ->through(function ($log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'last_name' => $log->user->last_name,
                        'email' => $log->user->email,
                    ] : null,
                    'action' => $log->action,
                    'module' => $log->module,
                    'entity' => $log->entity,
                    'entity_id' => $log->entity_id,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Lista de activity logs',
            'data' => $logs,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/activity-logs/summary",
     *     tags={"Activity Logs"},
     *     summary="Resumen de actividades",
     *     description="Retorna un resumen de actividades por acción y módulo.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Cantidad de días hacia atrás para generar el resumen",
     *         @OA\Schema(type="integer", default=7, example=7)
     *     ),
     *
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
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     )
     * )
     */
    public function summary(Request $request)
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $days = $validated['days'] ?? 7;

        $startDate = now()->subDays($days);
        $endDate = now();

        $summary = [
            'total_actions' => ActivityLog::whereBetween('created_at', [
                $startDate,
                $endDate,
            ])->count(),

            'by_action' => ActivityLog::whereBetween('created_at', [
                $startDate,
                $endDate,
            ])
                ->selectRaw('action, COUNT(*) as total')
                ->groupBy('action')
                ->pluck('total', 'action'),

            'by_module' => ActivityLog::whereBetween('created_at', [
                $startDate,
                $endDate,
            ])
                ->selectRaw('module, COUNT(*) as total')
                ->groupBy('module')
                ->pluck('total', 'module'),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Resumen de actividades',
            'data' => $summary,
        ]);
    }
}