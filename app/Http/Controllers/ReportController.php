<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Services\ActivityLoggerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="Reportes de confianza y seguridad entre usuarios"
 * )
 */
class ReportController extends Controller
{
    private function publicStorageUrl(
        ?string $path
    ): ?string {
        if (! $path) {
            return null;
        }

        if (filter_var(
            $path,
            FILTER_VALIDATE_URL
        )) {
            return $path;
        }

        return asset(Storage::url($path));
    }

    private function formatUser(
        ?User $user
    ): ?array {
        if (! $user) {
            return null;
        }

        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' =>
                $user->maternal_last_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo_url' =>
                $this->publicStorageUrl(
                    $user->profile_photo
                ),
            'is_active' => $user->is_active,
            'role' => $user->roles
                ->first()?->name,
        ];
    }

    private function formatReport(
        Report $report
    ): array {
        $report->loadMissing([
            'reporter.roles',
            'reported.roles',
        ]);

        return [
            'id' => $report->id,
            'reporter_id' =>
                $report->reporter_id,
            'reporter' =>
                $this->formatUser(
                    $report->reporter
                ),
            'reported_id' =>
                $report->reported_id,
            'reported' =>
                $this->formatUser(
                    $report->reported
                ),
            'reason' => $report->reason,
            'description' =>
                $report->description,
            'status' => $report->status,
            'created_at' =>
                $report->created_at,
            'updated_at' =>
                $report->updated_at,
        ];
    }

    private function paginationData(
        $paginator
    ): array {
        return [
            'reports' => collect(
                $paginator->items()
            )->map(
                fn (Report $report) =>
                    $this->formatReport(
                        $report
                    )
            )->values(),
            'pagination' => [
                'current_page' =>
                    $paginator->currentPage(),
                'last_page' =>
                    $paginator->lastPage(),
                'per_page' =>
                    $paginator->perPage(),
                'total' =>
                    $paginator->total(),
                'from' =>
                    $paginator->firstItem(),
                'to' =>
                    $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/reports",
     *     operationId="listReports",
     *     tags={"Reports"},
     *     summary="Listar reportes",
     *     description="El administrador consulta todos los reportes. Los demás usuarios consultan únicamente los reportes que enviaron.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending","reviewed","resolved"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="reported_id",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reportes obtenidos exitosamente"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $request->validate([
            'status' => [
                'nullable',
                'string',
                Rule::in(Report::STATUSES),
            ],
            'reported_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at'),
            ],
            'search' => [
                'nullable',
                'string',
                'max:150',
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Report::query()->with([
            'reporter.roles',
            'reported.roles',
        ]);

        if (! $authUser->hasRole('admin')) {
            $query->where(
                'reporter_id',
                $authUser->id
            );
        }

        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->input('status')
            );
        }

        if (
            $authUser->hasRole('admin')
            && $request->filled('reported_id')
        ) {
            $query->where(
                'reported_id',
                $request->integer(
                    'reported_id'
                )
            );
        }

        if ($request->filled('search')) {
            $search = trim(
                (string) $request->input(
                    'search'
                )
            );

            $query->where(
                function ($searchQuery) use (
                    $search
                ) {
                    $searchQuery
                        ->where(
                            'reason',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'description',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhereHas(
                            'reported',
                            function ($userQuery) use (
                                $search
                            ) {
                                $userQuery
                                    ->where(
                                        'name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'last_name',
                                        'like',
                                        "%{$search}%"
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        "%{$search}%"
                                    );
                            }
                        );
                }
            );
        }

        $paginator = $query
            ->latest('created_at')
            ->paginate(
                (int) $request->input(
                    'per_page',
                    15
                )
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Reportes obtenidos exitosamente',
            'data' =>
                $this->paginationData(
                    $paginator
                ),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/summary",
     *     operationId="reportsSummary",
     *     tags={"Reports"},
     *     summary="Obtener resumen administrativo de reportes",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Resumen obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Solo administradores"
     *     )
     * )
     */
    public function summary()
    {
        $authUser = auth('api')->user();

        if (
            ! $authUser
            || ! $authUser->hasRole('admin')
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Solo los administradores pueden consultar este resumen.',
            ], 403);
        }

        $total = Report::count();
        $pending = Report::where(
            'status',
            Report::STATUS_PENDING
        )->count();
        $reviewed = Report::where(
            'status',
            Report::STATUS_REVIEWED
        )->count();
        $resolved = Report::where(
            'status',
            Report::STATUS_RESOLVED
        )->count();

        $mostReportedUsers = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.last_name',
                'users.maternal_last_name',
                'users.email',
            ])
            ->selectRaw(
                'COUNT(reports.id) AS reports_count'
            )
            ->join(
                'reports',
                'reports.reported_id',
                '=',
                'users.id'
            )
            ->whereNull(
                'reports.deleted_at'
            )
            ->groupBy([
                'users.id',
                'users.name',
                'users.last_name',
                'users.maternal_last_name',
                'users.email',
            ])
            ->orderByDesc(
                'reports_count'
            )
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Resumen de reportes obtenido exitosamente',
            'data' => [
                'totals' => [
                    'total' => $total,
                    'pending' => $pending,
                    'reviewed' => $reviewed,
                    'resolved' => $resolved,
                ],
                'most_reported_users' =>
                    $mostReportedUsers,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reports",
     *     operationId="createReport",
     *     tags={"Reports"},
     *     summary="Reportar a otro usuario",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={
     *                 "reported_id",
     *                 "reason",
     *                 "description"
     *             },
     *
     *             @OA\Property(
     *                 property="reported_id",
     *                 type="integer",
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 example="Comportamiento inapropiado"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 example="El usuario utilizó lenguaje ofensivo durante la conversación."
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Reporte enviado exitosamente"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Datos inválidos"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        if (
            ! $authUser
            || ! $authUser->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Solo los usuarios activos pueden enviar reportes.',
            ], 403);
        }

        $validated = $request->validate([
            'reported_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->whereNull('deleted_at')
                    ->where('is_active', true),
                Rule::notIn([
                    $authUser->id,
                ]),
            ],
            'reason' => [
                'required',
                'string',
                'max:150',
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:5000',
            ],
        ]);

        $report = DB::transaction(
            function () use (
                $validated,
                $authUser
            ) {
                $report = Report::create([
                    'reporter_id' =>
                        $authUser->id,
                    'reported_id' =>
                        $validated[
                            'reported_id'
                        ],
                    'reason' =>
                        $validated['reason'],
                    'description' =>
                        $validated[
                            'description'
                        ],
                    'status' =>
                        Report::STATUS_PENDING,
                ]);

                ActivityLoggerService::logCreate(
                    module: 'REPORTS',
                    entity: 'reports',
                    entityId: $report->id,
                    description: "Report created against user ID {$report->reported_id}"
                );

                return $report;
            }
        );

        $report->load([
            'reporter.roles',
            'reported.roles',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Reporte enviado exitosamente',
            'data' => [
                'report' =>
                    $this->formatReport(
                        $report
                    ),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/reports/{id}",
     *     operationId="showReport",
     *     tags={"Reports"},
     *     summary="Obtener reporte por ID",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reporte obtenido exitosamente"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Sin permisos"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reporte no encontrado"
     *     )
     * )
     */
    public function show(int $id)
    {
        $authUser = auth('api')->user();

        $report = Report::with([
            'reporter.roles',
            'reported.roles',
        ])->find($id);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Reporte no encontrado.',
            ], 404);
        }

        if (
            ! $authUser
            || (
                ! $authUser->hasRole('admin')
                && $report->reporter_id
                    !== $authUser->id
            )
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'No tienes permisos para consultar este reporte.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Reporte obtenido exitosamente',
            'data' => [
                'report' =>
                    $this->formatReport(
                        $report
                    ),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/reports/{id}",
     *     operationId="updateReportStatus",
     *     tags={"Reports"},
     *     summary="Actualizar estado de un reporte",
     *     description="Solo los administradores pueden cambiar el estado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"status"},
     *
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={
     *                     "pending",
     *                     "reviewed",
     *                     "resolved"
     *                 }
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Estado actualizado"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Solo administradores"
     *     )
     * )
     */
    public function update(
        Request $request,
        int $id
    ) {
        $authUser = auth('api')->user();

        if (
            ! $authUser
            || ! $authUser->hasRole('admin')
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Solo los administradores pueden actualizar reportes.',
            ], 403);
        }

        $report = Report::find($id);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Reporte no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in(Report::STATUSES),
            ],
        ]);

        if (
            $report->status
                === Report::STATUS_RESOLVED
            && $validated['status']
                !== Report::STATUS_RESOLVED
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Un reporte resuelto no puede volver a un estado anterior.',
            ], 409);
        }

        DB::transaction(
            function () use (
                $report,
                $validated
            ) {
                $report->update([
                    'status' =>
                        $validated['status'],
                ]);

                ActivityLoggerService::logUpdate(
                    module: 'REPORTS',
                    entity: 'reports',
                    entityId: $report->id,
                    description: "Report ID {$report->id} status updated to {$report->status}"
                );
            }
        );

        $report->refresh();
        $report->load([
            'reporter.roles',
            'reported.roles',
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Estado del reporte actualizado exitosamente',
            'data' => [
                'report' =>
                    $this->formatReport(
                        $report
                    ),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/reports/{id}",
     *     operationId="deleteReport",
     *     tags={"Reports"},
     *     summary="Eliminar reporte",
     *     description="El autor puede retirar un reporte pendiente. El administrador puede eliminar cualquiera.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Reporte eliminado"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $authUser = auth('api')->user();
        $report = Report::find($id);

        if (! $report) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Reporte no encontrado.',
            ], 404);
        }

        $isAdmin =
            $authUser
            && $authUser->hasRole('admin');

        $isReporter =
            $authUser
            && $report->reporter_id
                === $authUser->id;

        if (! $isAdmin && ! $isReporter) {
            return response()->json([
                'success' => false,
                'message' =>
                    'No tienes permisos para eliminar este reporte.',
            ], 403);
        }

        if (
            ! $isAdmin
            && $report->status
                !== Report::STATUS_PENDING
        ) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Solo puedes retirar un reporte que todavía esté pendiente.',
            ], 409);
        }

        DB::transaction(
            function () use ($report) {
                ActivityLoggerService::logDelete(
                    module: 'REPORTS',
                    entity: 'reports',
                    entityId: $report->id,
                    description: "Report ID {$report->id} deleted"
                );

                $report->delete();
            }
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Reporte eliminado exitosamente.',
        ]);
    }
}