<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\ContractRequest;
use App\Models\FreelancerProfile;
use App\Models\Service;
use App\Models\User;
use App\Services\ActivityLoggerService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Contracts",
 *     description="Endpoints para la gestión de contrataciones formalizadas"
 * )
 */
class ContractController extends Controller
{
    private function buildPublicStorageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return asset(Storage::url($path));
    }

    private function formatRoleResponse($role): ?array
    {
        if (! $role) {
            return null;
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
        ];
    }

    private function formatUserResponse(?User $user): ?array
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
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_photo' => $user->profile_photo,
            'profile_photo_url' => $this->buildPublicStorageUrl(
                $user->profile_photo
            ),
            'is_active' => $user->is_active,
            'role' => $this->formatRoleResponse(
                $user->roles->first()
            ),
        ];
    }

    private function formatFreelancerProfileResponse(
        ?FreelancerProfile $profile
    ): ?array {
        if (! $profile) {
            return null;
        }

        $profile->loadMissing('user.roles');

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'user' => $this->formatUserResponse($profile->user),
            'description' => $profile->description,
            'specialty' => $profile->specialty,
            'location' => $profile->location,
            'service_area' => $profile->service_area,
            'work_mode' => $profile->work_mode,
            'experience' => $profile->experience,
            'rate_type' => $profile->rate_type,
            'rate' => $profile->rate,
            'languages' => $profile->languages ?? [],
            'available' => $profile->available,
            'average_rate' => $profile->average_rate,
        ];
    }

    private function formatServiceResponse(?Service $service): ?array
    {
        if (! $service) {
            return null;
        }

        return [
            'id' => $service->id,
            'freelancer_id' => $service->freelancer_id,
            'title' => $service->title,
            'description' => $service->description,
            'price' => $service->price,
            'category' => $service->category,
            'location' => $service->location,
            'is_active' => $service->is_active,
        ];
    }

    private function formatContractRequestResponse(
        ?ContractRequest $contractRequest
    ): ?array {
        if (! $contractRequest) {
            return null;
        }

        $contractRequest->loadMissing([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ]);

        return [
            'id' => $contractRequest->id,
            'client_id' => $contractRequest->client_id,
            'client' => $this->formatUserResponse(
                $contractRequest->client
            ),
            'freelancer_id' => $contractRequest->freelancer_id,
            'freelancer_profile' =>
                $this->formatFreelancerProfileResponse(
                    $contractRequest->freelancer
                ),
            'service_id' => $contractRequest->service_id,
            'service' => $this->formatServiceResponse(
                $contractRequest->service
            ),
            'description' => $contractRequest->description,
            'budget' => $contractRequest->budget,
            'status' => $contractRequest->status,
            'created_at' => $contractRequest->created_at,
            'updated_at' => $contractRequest->updated_at,
        ];
    }

    private function formatContractResponse(Contract $contract): array
    {
        $contract->loadMissing([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        return [
            'id' => $contract->id,
            'request_id' => $contract->request_id,
            'contract_request' =>
                $this->formatContractRequestResponse(
                    $contract->contractRequest
                ),
            'start_date' => $contract->start_date
                ? $contract->start_date->format('Y-m-d')
                : null,
            'end_date' => $contract->end_date
                ? $contract->end_date->format('Y-m-d')
                : null,
            'total_amount' => $contract->total_amount,
            'status' => $contract->status,
            'created_at' => $contract->created_at,
            'updated_at' => $contract->updated_at,
        ];
    }

    private function canViewContract(
        Contract $contract,
        User $user
    ): bool {
        $contract->loadMissing(
            'contractRequest.freelancer'
        );

        $contractRequest = $contract->contractRequest;

        if (! $contractRequest) {
            return false;
        }

        return $user->hasRole('admin')
            || $contractRequest->client_id === $user->id
            || (
                $contractRequest->freelancer
                && $contractRequest->freelancer->user_id
                    === $user->id
            );
    }

    private function canCreateContract(
        ContractRequest $contractRequest,
        User $user
    ): bool {
        $contractRequest->loadMissing('freelancer');

        return $user->hasRole('admin')
            || (
                $contractRequest->freelancer
                && $contractRequest->freelancer->user_id
                    === $user->id
            );
    }

    private function isClientOwner(
        Contract $contract,
        User $user
    ): bool {
        return $contract->contractRequest
            && $contract->contractRequest->client_id
                === $user->id;
    }

    private function isFreelancerOwner(
        Contract $contract,
        User $user
    ): bool {
        $contract->loadMissing(
            'contractRequest.freelancer'
        );

        return $contract->contractRequest
            && $contract->contractRequest->freelancer
            && $contract->contractRequest
                ->freelancer->user_id === $user->id;
    }

    /**
     * @OA\Get(
     *     path="/api/contracts",
     *     operationId="listContracts",
     *     tags={"Contracts"},
     *     summary="Listar contratos",
     *     description="El administrador consulta todos los contratos, el cliente sus contrataciones y el freelancer los contratos de sus servicios.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Contratos obtenidos exitosamente"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="No autorizado"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="El tipo de cuenta no tiene acceso al módulo"
     *     )
     * )
     */
    public function index()
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (
            ! $authUser->hasAnyRole([
                'admin',
                'cliente',
                'freelancer',
            ])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Tu tipo de cuenta no tiene acceso a los contratos.',
            ], 403);
        }

        $query = Contract::with([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        if (! $authUser->hasRole('admin')) {
            $isClient = $authUser->hasRole('cliente');
            $isFreelancer = $authUser->hasRole(
                'freelancer'
            );

            $query->where(function ($roleQuery) use (
                $authUser,
                $isClient,
                $isFreelancer
            ) {
                if ($isClient) {
                    $roleQuery->whereHas(
                        'contractRequest',
                        function ($requestQuery) use (
                            $authUser
                        ) {
                            $requestQuery->where(
                                'client_id',
                                $authUser->id
                            );
                        }
                    );
                }

                if ($isFreelancer) {
                    $method = $isClient
                        ? 'orWhereHas'
                        : 'whereHas';

                    $roleQuery->{$method}(
                        'contractRequest.freelancer',
                        function ($profileQuery) use (
                            $authUser
                        ) {
                            $profileQuery->where(
                                'user_id',
                                $authUser->id
                            );
                        }
                    );
                }
            });
        }

        $contracts = $query
            ->latest('created_at')
            ->get()
            ->map(
                fn (Contract $contract) =>
                    $this->formatContractResponse(
                        $contract
                    )
            )
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Contratos obtenidos exitosamente',
            'data' => [
                'contracts' => $contracts,
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/contracts",
     *     operationId="createContract",
     *     tags={"Contracts"},
     *     summary="Crear contrato",
     *     description="El freelancer propietario o un administrador formaliza una solicitud aceptada. El contrato siempre inicia con estado in_process. Si no se envía total_amount, se utiliza el presupuesto de la solicitud.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"request_id","start_date"},
     *
     *             @OA\Property(
     *                 property="request_id",
     *                 type="integer",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="start_date",
     *                 type="string",
     *                 format="date",
     *                 example="2026-07-23"
     *             ),
     *             @OA\Property(
     *                 property="end_date",
     *                 type="string",
     *                 format="date",
     *                 nullable=true,
     *                 example="2026-08-23"
     *             ),
     *             @OA\Property(
     *                 property="total_amount",
     *                 type="number",
     *                 format="float",
     *                 nullable=true,
     *                 example=1200.50,
     *                 description="Opcional si la solicitud tiene presupuesto"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Contrato creado exitosamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Sin permisos para formalizar la contratación"),
     *     @OA\Response(response=404, description="Solicitud no encontrada"),
     *     @OA\Response(response=409, description="La solicitud ya tiene un contrato"),
     *     @OA\Response(response=422, description="Datos inválidos o solicitud no aceptada")
     * )
     */
    public function store(Request $request)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (
            ! $authUser->hasRole('freelancer')
            && ! $authUser->hasRole('admin')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Solo el freelancer responsable o un administrador pueden crear el contrato.',
            ], 403);
        }

        $validated = $request->validate([
            'request_id' => [
                'required',
                'integer',
                Rule::exists(
                    'contract_requests',
                    'id'
                )->whereNull('deleted_at'),
            ],
            'start_date' => [
                'required',
                'date',
            ],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
            ],
            'total_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
        ]);

        $contractRequest = ContractRequest::with([
            'client.roles',
            'freelancer.user.roles',
            'service',
        ])->find($validated['request_id']);

        if (! $contractRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Solicitud de contratación no encontrada.',
            ], 404);
        }

        if (
            ! $this->canCreateContract(
                $contractRequest,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para formalizar esta contratación.',
            ], 403);
        }

        if ($contractRequest->status !== 'accepted') {
            return response()->json([
                'success' => false,
                'message' => 'Solo se puede crear un contrato desde una solicitud aceptada.',
                'data' => [
                    'current_status' =>
                        $contractRequest->status,
                ],
            ], 422);
        }

        if (
            ! $contractRequest->client
            || ! $contractRequest->client->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente de la solicitud no está activo.',
            ], 422);
        }

        if (
            ! $contractRequest->freelancer
            || ! $contractRequest->freelancer->user
            || ! $contractRequest->freelancer
                ->user->is_active
        ) {
            return response()->json([
                'success' => false,
                'message' => 'El freelancer de la solicitud no está activo.',
            ], 422);
        }

        if (
            Contract::withTrashed()
                ->where(
                    'request_id',
                    $contractRequest->id
                )
                ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Esta solicitud ya tiene un contrato formalizado.',
            ], 409);
        }

        $totalAmount =
            $validated['total_amount']
            ?? $contractRequest->budget;

        if ($totalAmount === null) {
            return response()->json([
                'success' => false,
                'message' => 'Debes indicar total_amount porque la solicitud no tiene presupuesto.',
                'errors' => [
                    'total_amount' => [
                        'El monto total es obligatorio cuando la solicitud no tiene presupuesto.',
                    ],
                ],
            ], 422);
        }

        try {
            $contract = DB::transaction(
                function () use (
                    $validated,
                    $contractRequest,
                    $totalAmount
                ) {
                    $lockedRequest =
                        ContractRequest::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $contractRequest->id
                            );

                    if (
                        $lockedRequest->status
                        !== 'accepted'
                    ) {
                        throw new \LogicException(
                            'CONTRACT_REQUEST_NOT_ACCEPTED'
                        );
                    }

                    if (
                        Contract::withTrashed()
                            ->where(
                                'request_id',
                                $lockedRequest->id
                            )
                            ->exists()
                    ) {
                        throw new \LogicException(
                            'CONTRACT_ALREADY_EXISTS'
                        );
                    }

                    $contract = Contract::create([
                        'request_id' =>
                            $lockedRequest->id,
                        'start_date' =>
                            $validated['start_date'],
                        'end_date' =>
                            $validated['end_date']
                                ?? null,
                        'total_amount' =>
                            $totalAmount,
                        'status' => 'in_process',
                    ]);

                    ActivityLoggerService::logCreate(
                        module: 'CONTRACTS',
                        entity: 'contracts',
                        entityId: $contract->id,
                        description: "Contract created from contract request ID {$lockedRequest->id}"
                    );

                    NotificationService::contractStatus(
                        $contractRequest->client_id,
                        Contract::STATUS_IN_PROCESS
                    );

                    NotificationService::contractStatus(
                        $contractRequest->freelancer->user_id,
                        Contract::STATUS_IN_PROCESS
                    );

                    return $contract;
                }
            );
        } catch (\LogicException $exception) {
            if (
                $exception->getMessage()
                === 'CONTRACT_ALREADY_EXISTS'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta solicitud ya tiene un contrato formalizado.',
                ], 409);
            }

            if (
                $exception->getMessage()
                === 'CONTRACT_REQUEST_NOT_ACCEPTED'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'La solicitud ya no se encuentra aceptada.',
                ], 409);
            }

            throw $exception;
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta solicitud ya tiene un contrato formalizado.',
                ], 409);
            }

            throw $exception;
        }

        $contract->load([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrato formalizado exitosamente',
            'data' => [
                'contract' =>
                    $this->formatContractResponse(
                        $contract
                    ),
            ],
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/contracts/{id}",
     *     operationId="showContract",
     *     tags={"Contracts"},
     *     summary="Obtener contrato por ID",
     *     description="Retorna el contrato al administrador, cliente o freelancer involucrado.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del contrato",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(response=200, description="Contrato obtenido exitosamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Sin permisos"),
     *     @OA\Response(response=404, description="Contrato no encontrado")
     * )
     */
    public function show(int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $contract = Contract::with([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ])->find($id);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado.',
            ], 404);
        }

        if (
            ! $this->canViewContract(
                $contract,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para ver este contrato.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contrato obtenido exitosamente',
            'data' => [
                'contract' =>
                    $this->formatContractResponse(
                        $contract
                    ),
            ],
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/contracts/{id}",
     *     operationId="updateContract",
     *     tags={"Contracts"},
     *     summary="Actualizar contrato",
     *     description="El administrador puede modificar fechas, monto y estado. El freelancer puede completar o cancelar. El cliente únicamente puede cancelar. Solo se modifican contratos en proceso.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del contrato",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             @OA\Property(property="start_date", type="string", format="date", example="2026-07-23"),
     *             @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2026-08-23"),
     *             @OA\Property(property="total_amount", type="number", format="float", example=1500.00),
     *             @OA\Property(property="status", type="string", enum={"in_process","completed","canceled"}, example="completed")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Contrato actualizado correctamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="La acción no está permitida para el usuario"),
     *     @OA\Response(response=404, description="Contrato no encontrado"),
     *     @OA\Response(response=409, description="El contrato ya fue completado o cancelado"),
     *     @OA\Response(response=422, description="Datos inválidos")
     * )
     */
    public function update(Request $request, int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        $contract = Contract::with([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ])->find($id);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado.',
            ], 404);
        }

        if (
            ! $this->canViewContract(
                $contract,
                $authUser
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este contrato.',
            ], 403);
        }

        if ($contract->status !== 'in_process') {
            return response()->json([
                'success' => false,
                'message' => 'El contrato ya fue finalizado y no puede modificarse.',
                'data' => [
                    'current_status' =>
                        $contract->status,
                ],
            ], 409);
        }

        $validated = $request->validate([
            'start_date' => [
                'sometimes',
                'required',
                'date',
            ],
            'end_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
            'total_amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'in_process',
                    'completed',
                    'canceled',
                ]),
            ],
        ]);

        if (empty($validated)) {
            return response()->json([
                'success' => false,
                'message' => 'Debes enviar al menos un campo para actualizar.',
            ], 422);
        }

        $isAdmin = $authUser->hasRole('admin');
        $isClientOwner = $this->isClientOwner(
            $contract,
            $authUser
        );
        $isFreelancerOwner =
            $this->isFreelancerOwner(
                $contract,
                $authUser
            );

        if (! $isAdmin && $isClientOwner) {
            if (
                count($validated) !== 1
                || ! isset($validated['status'])
                || $validated['status'] !== 'canceled'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cliente únicamente puede cancelar el contrato.',
                ], 403);
            }
        } elseif (! $isAdmin && $isFreelancerOwner) {
            if (
                count($validated) !== 1
                || ! isset($validated['status'])
                || ! in_array(
                    $validated['status'],
                    ['completed', 'canceled'],
                    true
                )
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'El freelancer únicamente puede completar o cancelar el contrato.',
                ], 403);
            }
        } elseif (! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para actualizar este contrato.',
            ], 403);
        }

        if (
            isset($validated['status'])
            && in_array(
                $validated['status'],
                ['completed', 'canceled'],
                true
            )
            && ! array_key_exists(
                'end_date',
                $validated
            )
            && ! $contract->end_date
        ) {
            $validated['end_date'] =
                now()->toDateString();
        }

        $startDate = array_key_exists(
            'start_date',
            $validated
        )
            ? Carbon::parse(
                $validated['start_date']
            )
            : $contract->start_date->copy();

        $endDate = array_key_exists(
            'end_date',
            $validated
        )
            ? (
                $validated['end_date']
                    ? Carbon::parse(
                        $validated['end_date']
                    )
                    : null
            )
            : (
                $contract->end_date
                    ? $contract->end_date->copy()
                    : null
            );

        if (
            $endDate
            && $endDate->lt($startDate)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
                'errors' => [
                    'end_date' => [
                        'La fecha final debe ser igual o posterior a la fecha inicial.',
                    ],
                ],
            ], 422);
        }

        DB::transaction(function () use (
            $contract,
            $validated
        ) {
            $contract->update($validated);

            ActivityLoggerService::logUpdate(
                module: 'CONTRACTS',
                entity: 'contracts',
                entityId: $contract->id,
                description: "Contract ID {$contract->id} updated"
            );

            if (
                isset($validated['status'])
                && in_array(
                    $validated['status'],
                    [
                        Contract::STATUS_COMPLETED,
                        Contract::STATUS_CANCELED,
                    ],
                    true
                )
            ) {
                $contract->loadMissing(
                    'contractRequest.freelancer'
                );

                NotificationService::contractStatus(
                    $contract->contractRequest->client_id,
                    $validated['status']
                );

                NotificationService::contractStatus(
                    $contract->contractRequest
                        ->freelancer
                        ->user_id,
                    $validated['status']
                );
            }
        });

        $contract->refresh();
        $contract->load([
            'contractRequest.client.roles',
            'contractRequest.freelancer.user.roles',
            'contractRequest.service',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contrato actualizado correctamente',
            'data' => [
                'contract' =>
                    $this->formatContractResponse(
                        $contract
                    ),
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/contracts/{id}",
     *     operationId="deleteContract",
     *     tags={"Contracts"},
     *     summary="Eliminar contrato",
     *     description="Elimina lógicamente un contrato. Solo los administradores pueden realizar esta acción.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID del contrato",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(response=200, description="Contrato eliminado correctamente"),
     *     @OA\Response(response=401, description="No autorizado"),
     *     @OA\Response(response=403, description="Solo administradores"),
     *     @OA\Response(response=404, description="Contrato no encontrado")
     * )
     */
    public function destroy(int $id)
    {
        $authUser = auth('api')->user();

        if (! $authUser) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        if (! $authUser->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Solo los administradores pueden eliminar contratos.',
            ], 403);
        }

        $contract = Contract::find($id);

        if (! $contract) {
            return response()->json([
                'success' => false,
                'message' => 'Contrato no encontrado.',
            ], 404);
        }

        DB::transaction(function () use ($contract) {
            ActivityLoggerService::logDelete(
                module: 'CONTRACTS',
                entity: 'contracts',
                entityId: $contract->id,
                description: "Contract ID {$contract->id} deleted"
            );

            $contract->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Contrato eliminado correctamente',
        ]);
    }
}