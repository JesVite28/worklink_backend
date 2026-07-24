<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Contract;
use App\Models\FreelancerProfile;
use App\Models\Review;
use App\Models\User;
use App\Services\ActivityLoggerService;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Reviews",
 *     description="Calificaciones entre participantes de contratos completados"
 * )
 */
class ReviewController extends Controller
{
    private function publicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset(Storage::url($path));
    }

    private function formatUser(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $user->loadMissing('roles');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'maternal_last_name' => $user->maternal_last_name,
            'profile_photo_url' =>
                $this->publicStorageUrl(
                    $user->profile_photo
                ),
            'is_active' => $user->is_active,
            'role' => $user->roles->first()?->name,
        ];
    }

    private function formatReview(Review $review): array
    {
        $review->loadMissing([
            'evaluator.roles',
            'evaluated.roles',
            'contract.contractRequest.service',
        ]);

        return [
            'id' => $review->id,
            'contract_id' => $review->contract_id,
            'evaluator_id' => $review->evaluator_id,
            'evaluator' => $this->formatUser(
                $review->evaluator
            ),
            'evaluated_id' => $review->evaluated_id,
            'evaluated' => $this->formatUser(
                $review->evaluated
            ),
            'rating' => $review->rating,
            'comment' => $review->comment,
            'service' => $review->contract
                ?->contractRequest
                ?->service
                ? [
                    'id' => $review->contract
                        ->contractRequest
                        ->service
                        ->id,
                    'title' => $review->contract
                        ->contractRequest
                        ->service
                        ->title,
                ]
                : null,
            'created_at' => $review->created_at,
            'updated_at' => $review->updated_at,
        ];
    }

    private function recalculateAverage(
        int $evaluatedUserId
    ): void {
        $average = Review::query()
            ->where(
                'evaluated_id',
                $evaluatedUserId
            )
            ->avg('rating');

        $average = $average !== null
            ? round((float) $average, 2)
            : null;

        FreelancerProfile::query()
            ->where('user_id', $evaluatedUserId)
            ->update(['average_rate' => $average]);

        CompanyProfile::query()
            ->where('user_id', $evaluatedUserId)
            ->update(['average_rate' => $average]);
    }

    private function participantIds(
        Contract $contract
    ): ?array {
        $contract->loadMissing(
            'contractRequest.freelancer.user'
        );

        $request = $contract->contractRequest;
        $freelancerUserId =
            $request?->freelancer?->user_id;

        if (! $request || ! $freelancerUserId) {
            return null;
        }

        return [
            'client_id' => $request->client_id,
            'freelancer_user_id' => $freelancerUserId,
        ];
    }

    private function canView(
        Review $review,
        User $user
    ): bool {
        return $user->hasRole('admin')
            || $review->evaluator_id === $user->id
            || $review->evaluated_id === $user->id;
    }

    /**
     * @OA\Get(
     *     path="/api/public/reviews/user/{userId}",
     *     operationId="publicReviewsByUser",
     *     tags={"Reviews"},
     *     summary="Consultar calificaciones recibidas por usuario",
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calificaciones obtenidas"
     *     )
     * )
     */
    public function publicByUser(
        Request $request,
        int $userId
    ) {
        $user = User::query()
            ->where('is_active', true)
            ->find($userId);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado.',
            ], 404);
        }

        $perPage = min(
            max(
                (int) $request->input(
                    'per_page',
                    10
                ),
                1
            ),
            50
        );

        $paginator = Review::with([
            'evaluator.roles',
            'evaluated.roles',
            'contract.contractRequest.service',
        ])
            ->where('evaluated_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);

        $average = Review::query()
            ->where('evaluated_id', $user->id)
            ->avg('rating');

        return response()->json([
            'success' => true,
            'message' => 'Calificaciones obtenidas exitosamente',
            'data' => [
                'user' => $this->formatUser($user),
                'average_rating' => $average !== null
                    ? round((float) $average, 2)
                    : null,
                'reviews_count' => $paginator->total(),
                'reviews' => collect(
                    $paginator->items()
                )->map(
                    fn (Review $review) =>
                        $this->formatReview($review)
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
                ],
            ],
        ]);
    }

    public function publicByFreelancer(
        Request $request,
        int $freelancerId
    ) {
        $profile = FreelancerProfile::query()
            ->whereHas(
                'user',
                fn ($query) =>
                    $query->where('is_active', true)
            )
            ->find($freelancerId);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil freelancer no encontrado.',
            ], 404);
        }

        return $this->publicByUser(
            $request,
            $profile->user_id
        );
    }

    public function publicByCompany(
        Request $request,
        int $companyId
    ) {
        $profile = CompanyProfile::query()
            ->whereHas(
                'user',
                fn ($query) =>
                    $query->where('is_active', true)
            )
            ->find($companyId);

        if (! $profile) {
            return response()->json([
                'success' => false,
                'message' => 'Perfil empresarial no encontrado.',
            ], 404);
        }

        return $this->publicByUser(
            $request,
            $profile->user_id
        );
    }

    /**
     * @OA\Get(
     *     path="/api/reviews",
     *     operationId="listReviews",
     *     tags={"Reviews"},
     *     summary="Listar calificaciones relacionadas con el usuario",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Calificaciones obtenidas"
     *     )
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
            'rating' => [
                'nullable',
                'integer',
                'between:1,5',
            ],
            'type' => [
                'nullable',
                Rule::in([
                    'given',
                    'received',
                ]),
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Review::with([
            'evaluator.roles',
            'evaluated.roles',
            'contract.contractRequest.service',
        ]);

        if (! $user->hasRole('admin')) {
            if ($request->input('type') === 'given') {
                $query->where(
                    'evaluator_id',
                    $user->id
                );
            } elseif (
                $request->input('type')
                    === 'received'
            ) {
                $query->where(
                    'evaluated_id',
                    $user->id
                );
            } else {
                $query->where(
                    fn ($roleQuery) =>
                        $roleQuery
                            ->where(
                                'evaluator_id',
                                $user->id
                            )
                            ->orWhere(
                                'evaluated_id',
                                $user->id
                            )
                );
            }
        }

        if ($request->filled('rating')) {
            $query->where(
                'rating',
                $request->integer('rating')
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
            'message' => 'Calificaciones obtenidas exitosamente',
            'data' => [
                'reviews' => collect(
                    $paginator->items()
                )->map(
                    fn (Review $review) =>
                        $this->formatReview($review)
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
                ],
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/reviews",
     *     operationId="createReview",
     *     tags={"Reviews"},
     *     summary="Calificar al otro participante de un contrato completado",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"contract_id","rating"},
     *             @OA\Property(
     *                 property="contract_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="rating",
     *                 type="integer",
     *                 minimum=1,
     *                 maximum=5,
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="comment",
     *                 type="string",
     *                 nullable=true,
     *                 example="Excelente trabajo y comunicación."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Calificación creada"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El usuario no pertenece al contrato"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="El contrato no está completado o ya fue calificado"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $user = auth('api')->user();

        if (! $user || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los usuarios activos pueden publicar calificaciones.',
            ], 403);
        }

        $validated = $request->validate([
            'contract_id' => [
                'required',
                'integer',
                Rule::exists('contracts', 'id')
                    ->whereNull('deleted_at'),
            ],
            'rating' => [
                'required',
                'integer',
                'between:1,5',
            ],
            'comment' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        try {
            $result = DB::transaction(
                function () use (
                    $validated,
                    $user
                ) {
                    $contract = Contract::query()
                        ->with(
                            'contractRequest.freelancer.user'
                        )
                        ->lockForUpdate()
                        ->find(
                            $validated['contract_id']
                        );

                    if (
                        ! $contract
                        || $contract->status
                            !== Contract::STATUS_COMPLETED
                    ) {
                        throw new \RuntimeException(
                            'CONTRACT_NOT_COMPLETED'
                        );
                    }

                    $participants =
                        $this->participantIds(
                            $contract
                        );

                    if (! $participants) {
                        throw new \RuntimeException(
                            'INVALID_CONTRACT'
                        );
                    }

                    if (
                        $user->id
                            === $participants['client_id']
                    ) {
                        $evaluatedId =
                            $participants[
                                'freelancer_user_id'
                            ];
                    } elseif (
                        $user->id
                            === $participants[
                                'freelancer_user_id'
                            ]
                    ) {
                        $evaluatedId =
                            $participants['client_id'];
                    } else {
                        throw new \RuntimeException(
                            'NOT_CONTRACT_PARTICIPANT'
                        );
                    }

                    $evaluated = User::query()
                        ->where('is_active', true)
                        ->find($evaluatedId);

                    if (! $evaluated) {
                        throw new \RuntimeException(
                            'EVALUATED_NOT_AVAILABLE'
                        );
                    }

                    $review = Review::withTrashed()
                        ->where(
                            'contract_id',
                            $contract->id
                        )
                        ->where(
                            'evaluator_id',
                            $user->id
                        )
                        ->lockForUpdate()
                        ->first();

                    if (
                        $review
                        && ! $review->trashed()
                    ) {
                        throw new \RuntimeException(
                            'REVIEW_ALREADY_EXISTS'
                        );
                    }

                    $restored = false;

                    if ($review && $review->trashed()) {
                        $review->restore();
                        $review->update([
                            'evaluated_id' =>
                                $evaluatedId,
                            'rating' =>
                                $validated['rating'],
                            'comment' =>
                                $validated['comment']
                                ?? null,
                        ]);
                        $restored = true;
                    } else {
                        $review = Review::create([
                            'contract_id' =>
                                $contract->id,
                            'evaluator_id' =>
                                $user->id,
                            'evaluated_id' =>
                                $evaluatedId,
                            'rating' =>
                                $validated['rating'],
                            'comment' =>
                                $validated['comment']
                                ?? null,
                        ]);
                    }

                    $this->recalculateAverage(
                        $evaluatedId
                    );

                    ActivityLoggerService::logCreate(
                        module: 'REVIEWS',
                        entity: 'reviews',
                        entityId: $review->id,
                        description: "Review created for contract ID {$contract->id}"
                    );

                    NotificationService::reviewReceived(
                        $evaluatedId,
                        (int) $validated['rating']
                    );

                    return [
                        'review' => $review,
                        'restored' => $restored,
                    ];
                }
            );
        } catch (\RuntimeException $exception) {
            return match (
                $exception->getMessage()
            ) {
                'CONTRACT_NOT_COMPLETED' =>
                    response()->json([
                        'success' => false,
                        'message' => 'Solo se puede calificar un contrato completado.',
                    ], 409),

                'NOT_CONTRACT_PARTICIPANT' =>
                    response()->json([
                        'success' => false,
                        'message' => 'No formas parte de este contrato.',
                    ], 403),

                'REVIEW_ALREADY_EXISTS' =>
                    response()->json([
                        'success' => false,
                        'message' => 'Ya calificaste este contrato.',
                    ], 409),

                'EVALUATED_NOT_AVAILABLE' =>
                    response()->json([
                        'success' => false,
                        'message' => 'El usuario que recibiría la calificación no está disponible.',
                    ], 422),

                default => response()->json([
                    'success' => false,
                    'message' => 'El contrato no tiene participantes válidos.',
                ], 422),
            };
        } catch (QueryException $exception) {
            if (
                (string) $exception->getCode()
                    === '23000'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya calificaste este contrato.',
                ], 409);
            }

            throw $exception;
        }

        $review = $result['review'];
        $review->load([
            'evaluator.roles',
            'evaluated.roles',
            'contract.contractRequest.service',
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['restored']
                ? 'Calificación restaurada y actualizada exitosamente'
                : 'Calificación creada exitosamente',
            'data' => [
                'review' =>
                    $this->formatReview($review),
            ],
        ], $result['restored'] ? 200 : 201);
    }

    /**
     * @OA\Get(
     *     path="/api/reviews/{id}",
     *     operationId="showReview",
     *     tags={"Reviews"},
     *     summary="Obtener calificación por ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calificación obtenida"
     *     )
     * )
     */
    public function show(int $id)
    {
        $user = auth('api')->user();
        $review = Review::with([
            'evaluator.roles',
            'evaluated.roles',
            'contract.contractRequest.service',
        ])->find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Calificación no encontrada.',
            ], 404);
        }

        if (! $user || ! $this->canView(
            $review,
            $user
        )) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para consultar esta calificación.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Calificación obtenida exitosamente',
            'data' => [
                'review' =>
                    $this->formatReview($review),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/reviews/{id}",
     *     operationId="updateReview",
     *     tags={"Reviews"},
     *     summary="Actualizar una calificación propia",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="rating",
     *                 type="integer",
     *                 minimum=1,
     *                 maximum=5
     *             ),
     *             @OA\Property(
     *                 property="comment",
     *                 type="string",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calificación actualizada"
     *     )
     * )
     */
    public function update(
        Request $request,
        int $id
    ) {
        $user = auth('api')->user();
        $review = Review::find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Calificación no encontrada.',
            ], 404);
        }

        if (
            ! $user
            || $review->evaluator_id !== $user->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Solo quien publicó la calificación puede modificarla.',
            ], 403);
        }

        $validated = $request->validate([
            'rating' => [
                'sometimes',
                'required',
                'integer',
                'between:1,5',
            ],
            'comment' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        DB::transaction(function () use (
            $review,
            $validated
        ) {
            $review->update($validated);

            $this->recalculateAverage(
                $review->evaluated_id
            );

            ActivityLoggerService::logUpdate(
                module: 'REVIEWS',
                entity: 'reviews',
                entityId: $review->id,
                description: "Review ID {$review->id} updated"
            );
        });

        $review->refresh()->load([
            'evaluator.roles',
            'evaluated.roles',
            'contract.contractRequest.service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Calificación actualizada exitosamente',
            'data' => [
                'review' =>
                    $this->formatReview($review),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     operationId="deleteReview",
     *     tags={"Reviews"},
     *     summary="Eliminar calificación",
     *     description="Puede eliminarla su autor o un administrador.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calificación eliminada"
     *     )
     * )
     */
    public function destroy(int $id)
    {
        $user = auth('api')->user();
        $review = Review::find($id);

        if (! $review) {
            return response()->json([
                'success' => false,
                'message' => 'Calificación no encontrada.',
            ], 404);
        }

        if (
            ! $user
            || (
                $review->evaluator_id !== $user->id
                && ! $user->hasRole('admin')
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar esta calificación.',
            ], 403);
        }

        $evaluatedId = $review->evaluated_id;

        DB::transaction(function () use (
            $review,
            $evaluatedId
        ) {
            ActivityLoggerService::logDelete(
                module: 'REVIEWS',
                entity: 'reviews',
                entityId: $review->id,
                description: "Review ID {$review->id} deleted"
            );

            $review->delete();

            $this->recalculateAverage(
                $evaluatedId
            );
        });

        return response()->json([
            'success' => true,
            'message' => 'Calificación eliminada exitosamente.',
        ]);
    }
}